# Инструкция по перезагрузке приложения

## После изменений кода нужно выполнить:

### 1. Очистка кэшей (уже сделано):
```bash
php artisan optimize:clear
```

### 2. Перезапуск Queue Worker (ОБЯЗАТЕЛЬНО!):
```bash
php artisan queue:restart
```

**Важно:** Queue worker загружает код при старте, поэтому после изменений в `SendNotificationJob`, `EmailService`, `WhatsAppService` нужно перезапустить worker.

### 3. Если используется Supervisor:

```bash
# Проверить статус
sudo supervisorctl status

# Перезапустить queue worker
sudo supervisorctl restart notifybus-worker:*

# Или если процесс называется по-другому
sudo supervisorctl restart notiify-worker:*

# Перезапустить MySQL sync (если нужно)
sudo supervisorctl restart notifybus-mysql-sync
```

### 4. Если queue worker запущен вручную:

Найдите процесс и перезапустите:
```bash
# Найти процесс
ps aux | grep "queue:work"

# Убить процесс (он автоматически перезапустится, если используется supervisor)
# Или просто перезапустить через supervisorctl
```

## Быстрая команда (все сразу):

```bash
php artisan optimize:clear && \
php artisan queue:restart && \
sudo supervisorctl restart notifybus-worker:* && \
sudo supervisorctl restart notifybus-mysql-sync
```

## Проверка, что все работает:

```bash
# Проверить логи queue worker
tail -f storage/logs/laravel.log | grep "✅\|❌"

# Проверить статус supervisor
sudo supervisorctl status

# Проверить, что worker обрабатывает задачи
php artisan queue:work --help
```

