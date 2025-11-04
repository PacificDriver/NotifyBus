#!/bin/bash

# Скрипт для исправления ошибки 403 Forbidden в Nginx
# Использование: sudo bash fix-nginx-403.sh

echo "🔧 Исправление ошибки 403 Forbidden в Nginx..."

# Определяем путь к проекту
PROJECT_PATH="/var/www/html/NotifyBus"
PUBLIC_PATH="$PROJECT_PATH/public"

# Проверяем, существует ли путь
if [ ! -d "$PROJECT_PATH" ]; then
    echo "❌ Директория $PROJECT_PATH не найдена!"
    echo "Укажите правильный путь к проекту."
    exit 1
fi

echo "📝 Исправление прав доступа..."

# Создаем необходимые директории
mkdir -p "$PUBLIC_PATH"
mkdir -p "$PROJECT_PATH/storage/logs"
mkdir -p "$PROJECT_PATH/bootstrap/cache"

# Определяем пользователя веб-сервера
WEB_USER="www-data"
if ! id "$WEB_USER" &>/dev/null; then
    WEB_USER="nginx"
    if ! id "$WEB_USER" &>/dev/null; then
        echo "⚠️  Пользователь веб-сервера не найден. Используем текущего пользователя."
        WEB_USER=$(whoami)
    fi
fi

echo "Используем пользователя: $WEB_USER"

# Устанавливаем права
echo "📝 Устанавливаем права доступа..."

# Владелец для всех файлов проекта
sudo chown -R "$WEB_USER:$WEB_USER" "$PROJECT_PATH"

# Права на выполнение для директорий
sudo find "$PROJECT_PATH" -type d -exec chmod 755 {} \;

# Права на чтение для файлов
sudo find "$PROJECT_PATH" -type f -exec chmod 644 {} \;

# Права на запись для storage и bootstrap/cache
sudo chmod -R 775 "$PROJECT_PATH/storage"
sudo chmod -R 775 "$PROJECT_PATH/bootstrap/cache"

# Особые права для public/index.php
if [ -f "$PUBLIC_PATH/index.php" ]; then
    sudo chmod 644 "$PUBLIC_PATH/index.php"
    sudo chown "$WEB_USER:$WEB_USER" "$PUBLIC_PATH/index.php"
fi

# Проверяем конфигурацию Nginx
echo ""
echo "📋 Проверка конфигурации Nginx..."

NGINX_CONF=$(find /etc/nginx/sites-available -name "*notiify*" -o -name "*NotifyBus*" | head -1)

if [ -z "$NGINX_CONF" ]; then
    echo "⚠️  Конфигурация Nginx не найдена в sites-available"
    echo "Создайте конфигурацию вручную или проверьте путь к проекту в существующей конфигурации"
else
    echo "Найдена конфигурация: $NGINX_CONF"
    echo ""
    echo "Проверьте, что в конфигурации указан правильный путь:"
    echo "  root $PUBLIC_PATH;"
    echo ""
    echo "И правильный пользователь:"
    echo "  user $WEB_USER;"
fi

echo ""
echo "📋 Проверка структуры:"
ls -la "$PUBLIC_PATH" | head -5
echo ""
echo "Права на storage:"
ls -ld "$PROJECT_PATH/storage"

echo ""
echo "✅ Права доступа установлены"
echo ""
echo "Проверьте конфигурацию Nginx:"
echo "  sudo nginx -t"
echo ""
echo "Перезагрузите Nginx:"
echo "  sudo systemctl reload nginx"
echo ""
echo "Если проблема сохраняется, проверьте:"
echo "  1. Правильность пути root в конфигурации Nginx"
echo "  2. Существование файла $PUBLIC_PATH/index.php"
echo "  3. Логи Nginx: sudo tail -f /var/log/nginx/error.log"

