#!/bin/bash

# Скрипт для исправления ошибки "File not found" в Nginx
# Использование: sudo bash fix-nginx-file-not-found.sh

echo "🔧 Исправление ошибки 'File not found' в Nginx..."

PROJECT_PATH="/var/www/html/NotifyBus"
PUBLIC_PATH="$PROJECT_PATH/public"
INDEX_FILE="$PUBLIC_PATH/index.php"

# Проверяем существование файла index.php
if [ ! -f "$INDEX_FILE" ]; then
    echo "❌ Файл $INDEX_FILE не найден!"
    echo "Создаю базовый файл index.php..."
    
    sudo mkdir -p "$PUBLIC_PATH"
    
    cat > /tmp/index.php << 'EOF'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
EOF

    sudo mv /tmp/index.php "$INDEX_FILE"
    sudo chown www-data:www-data "$INDEX_FILE"
    sudo chmod 644 "$INDEX_FILE"
    echo "✅ Файл index.php создан"
else
    echo "✅ Файл index.php существует"
fi

# Проверяем PHP-FPM
echo ""
echo "📋 Проверка PHP-FPM..."

PHP_VERSION=$(php -v | head -1 | cut -d' ' -f2 | cut -d'.' -f1,2)
PHP_FPM_SOCK="/var/run/php/php${PHP_VERSION}-fpm.sock"

if [ ! -S "$PHP_FPM_SOCK" ]; then
    echo "⚠️  Сокет PHP-FPM не найден: $PHP_FPM_SOCK"
    echo "Проверяем альтернативные пути..."
    
    # Проверяем другие возможные пути
    if [ -S "/run/php/php${PHP_VERSION}-fpm.sock" ]; then
        PHP_FPM_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
        echo "✅ Найден сокет: $PHP_FPM_SOCK"
    elif [ -S "/var/run/php-fpm/php-fpm.sock" ]; then
        PHP_FPM_SOCK="/var/run/php-fpm/php-fpm.sock"
        echo "✅ Найден сокет: $PHP_FPM_SOCK"
    else
        echo "❌ Сокет PHP-FPM не найден!"
        echo "Проверьте статус PHP-FPM:"
        echo "  sudo systemctl status php${PHP_VERSION}-fpm"
        echo "  sudo systemctl start php${PHP_VERSION}-fpm"
        exit 1
    fi
else
    echo "✅ Сокет PHP-FPM найден: $PHP_FPM_SOCK"
fi

# Проверяем конфигурацию Nginx
echo ""
echo "📋 Проверка конфигурации Nginx..."

NGINX_CONF=$(find /etc/nginx/sites-available -name "*notiify*" -o -name "*NotifyBus*" 2>/dev/null | head -1)

if [ -z "$NGINX_CONF" ]; then
    echo "⚠️  Конфигурация не найдена в sites-available"
    echo "Проверяем sites-enabled..."
    NGINX_CONF=$(find /etc/nginx/sites-enabled -name "*notiify*" -o -name "*NotifyBus*" 2>/dev/null | head -1)
fi

if [ -n "$NGINX_CONF" ]; then
    echo "Найдена конфигурация: $NGINX_CONF"
    echo ""
    echo "Проверьте следующие параметры:"
    echo ""
    echo "1. Путь root должен быть:"
    echo "   root $PUBLIC_PATH;"
    echo ""
    echo "2. FastCGI должен указывать на:"
    echo "   fastcgi_pass unix:$PHP_FPM_SOCK;"
    echo ""
    echo "3. SCRIPT_FILENAME должен быть:"
    echo "   fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;"
    echo ""
    
    # Показываем текущую конфигурацию
    echo "Текущая конфигурация root:"
    grep -E "^\s*root\s+" "$NGINX_CONF" || echo "  Не найдено"
    
    echo ""
    echo "Текущая конфигурация fastcgi_pass:"
    grep -E "fastcgi_pass" "$NGINX_CONF" || echo "  Не найдено"
else
    echo "⚠️  Конфигурация Nginx не найдена"
    echo "Создайте конфигурацию вручную"
fi

# Проверяем права доступа
echo ""
echo "📋 Проверка прав доступа..."

if [ -f "$INDEX_FILE" ]; then
    ls -la "$INDEX_FILE"
    sudo chown www-data:www-data "$INDEX_FILE"
    sudo chmod 644 "$INDEX_FILE"
fi

# Проверяем структуру директорий
echo ""
echo "📋 Структура проекта:"
ls -la "$PUBLIC_PATH" | head -5

echo ""
echo "✅ Проверка завершена"
echo ""
echo "Следующие шаги:"
echo "1. Проверьте конфигурацию Nginx:"
echo "   sudo nginx -t"
echo ""
echo "2. Проверьте логи Nginx:"
echo "   sudo tail -20 /var/log/nginx/error.log"
echo ""
echo "3. Проверьте статус PHP-FPM:"
echo "   sudo systemctl status php${PHP_VERSION}-fpm"
echo ""
echo "4. Перезагрузите Nginx:"
echo "   sudo systemctl reload nginx"

