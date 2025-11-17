#!/bin/bash

# Скрипт для перезагрузки приложения после изменений кода

echo "🔄 Перезагрузка приложения Laravel..."

# 1. Очистка всех кэшей
echo "1. Очистка кэшей..."
php artisan optimize:clear
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 2. Перезапуск queue worker (важно для применения изменений в SendNotificationJob)
echo "2. Перезапуск queue worker..."
php artisan queue:restart

# 3. Если используется supervisor, перезапустить процессы
echo "3. Перезапуск supervisor процессов..."
if command -v supervisorctl &> /dev/null; then
    # Перезапуск queue worker
    sudo supervisorctl restart notifybus-worker:* 2>/dev/null || echo "   Queue worker не найден в supervisor"
    
    # Перезапуск MySQL sync (если нужно)
    sudo supervisorctl restart notifybus-mysql-sync 2>/dev/null || echo "   MySQL sync не найден в supervisor"
    
    # Перезагрузка конфигурации supervisor
    sudo supervisorctl reread
    sudo supervisorctl update
else
    echo "   Supervisor не установлен, пропускаем"
fi

echo ""
echo "✅ Перезагрузка завершена!"
echo ""
echo "Проверьте статус процессов:"
echo "  - Queue worker: php artisan queue:work --help"
echo "  - Supervisor: sudo supervisorctl status"

