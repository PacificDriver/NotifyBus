#!/bin/bash

# Скрипт для исправления неправильного пути в конфигурации Nginx
# Использование: sudo bash fix-nginx-path.sh

echo "🔧 Исправление пути в конфигурации Nginx..."

# Определяем правильный путь
CORRECT_PATH="/var/www/html/NotifyBus/public"
WRONG_PATH="/var/www/notiify/public"

# Проверяем, существует ли правильный путь
if [ ! -d "$CORRECT_PATH" ]; then
    echo "❌ Директория $CORRECT_PATH не найдена!"
    echo "Проверьте правильность пути к проекту."
    exit 1
fi

echo "✅ Правильный путь найден: $CORRECT_PATH"

# Ищем конфигурацию Nginx
NGINX_CONF=$(find /etc/nginx/sites-available -name "*notiify*" -o -name "*NotifyBus*" 2>/dev/null | head -1)

if [ -z "$NGINX_CONF" ]; then
    echo "⚠️  Конфигурация не найдена в sites-available"
    echo "Проверяем sites-enabled..."
    NGINX_CONF=$(find /etc/nginx/sites-enabled -name "*notiify*" -o -name "*NotifyBus*" 2>/dev/null | head -1)
fi

if [ -z "$NGINX_CONF" ]; then
    echo "⚠️  Конфигурация не найдена"
    echo "Ищем все конфигурации с 'notiify' или 'NotifyBus'..."
    NGINX_CONF=$(grep -r "notiify\|NotifyBus" /etc/nginx/sites-available /etc/nginx/sites-enabled 2>/dev/null | head -1 | cut -d: -f1)
fi

if [ -z "$NGINX_CONF" ]; then
    echo "❌ Конфигурация не найдена!"
    echo "Создайте конфигурацию вручную или укажите путь к файлу."
    exit 1
fi

echo "Найдена конфигурация: $NGINX_CONF"

# Делаем резервную копию
BACKUP_FILE="${NGINX_CONF}.backup.$(date +%Y%m%d_%H%M%S)"
sudo cp "$NGINX_CONF" "$BACKUP_FILE"
echo "✅ Резервная копия создана: $BACKUP_FILE"

# Показываем текущие настройки
echo ""
echo "📋 Текущие настройки root:"
grep -E "^\s*root\s+" "$NGINX_CONF" || echo "  Не найдено"

# Заменяем неправильный путь
if grep -q "$WRONG_PATH" "$NGINX_CONF"; then
    echo ""
    echo "📝 Исправление пути..."
    sudo sed -i "s|$WRONG_PATH|$CORRECT_PATH|g" "$NGINX_CONF"
    echo "✅ Путь исправлен"
elif grep -q "/var/www/notiify" "$NGINX_CONF"; then
    echo ""
    echo "📝 Исправление пути..."
    sudo sed -i "s|/var/www/notiify/public|$CORRECT_PATH|g" "$NGINX_CONF"
    sudo sed -i "s|/var/www/notiify|/var/www/html/NotifyBus|g" "$NGINX_CONF"
    echo "✅ Путь исправлен"
else
    # Проверяем, есть ли вообще правильный путь
    if ! grep -q "$CORRECT_PATH" "$NGINX_CONF"; then
        echo ""
        echo "📝 Добавление правильного пути..."
        # Заменяем любые упоминания /var/www/ на правильный путь
        sudo sed -i "s|root /var/www/[^;]*;|root $CORRECT_PATH;|g" "$NGINX_CONF"
        echo "✅ Путь обновлен"
    else
        echo "✅ Путь уже правильный"
    fi
fi

# Показываем новые настройки
echo ""
echo "📋 Новые настройки root:"
grep -E "^\s*root\s+" "$NGINX_CONF" || echo "  Не найдено"

# Проверяем конфигурацию
echo ""
echo "📋 Проверка конфигурации Nginx..."
if sudo nginx -t 2>&1 | grep -q "successful"; then
    echo "✅ Конфигурация корректна"
    echo ""
    echo "Перезагружаем Nginx..."
    sudo systemctl reload nginx
    echo "✅ Nginx перезагружен"
else
    echo "❌ Ошибка в конфигурации:"
    sudo nginx -t
    echo ""
    echo "Восстанавливаем из резервной копии..."
    sudo cp "$BACKUP_FILE" "$NGINX_CONF"
    exit 1
fi

echo ""
echo "✅ Готово!"
echo ""
echo "Проверьте сайт в браузере. Если проблема сохраняется:"
echo "  1. Проверьте логи: sudo tail -f /var/log/nginx/error.log"
echo "  2. Убедитесь, что файл существует: ls -la $CORRECT_PATH/index.php"
echo "  3. Проверьте права: sudo chown -R www-data:www-data /var/www/html/NotifyBus"

