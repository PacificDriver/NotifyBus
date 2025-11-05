# 🚀 Установка Laravel Sanctum

## Шаг 0: Исправление прав доступа (если нужно)

Если возникла ошибка "Permission denied" для логов:

```bash
cd /var/www/html/NotifyBus
sudo bash fix-permissions-complete.sh
```

**ВАЖНО:** После исправления прав используйте `sudo -u www-data` для запуска команд artisan:

```bash
# Правильно:
sudo -u www-data php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Неправильно (будет ошибка прав):
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

Или вручную:

```bash
sudo mkdir -p storage/logs
sudo touch storage/logs/laravel.log
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo usermod -a -G www-data $USER
# Перелогиниться или выполнить: newgrp www-data
```

## Шаг 1: Установка через Composer

```bash
cd /var/www/html/NotifyBus
composer require laravel/sanctum
```

## Шаг 2: Публикация конфигурации

**ВАЖНО:** Запустите команду от имени пользователя www-data или с sudo:

```bash
# Вариант 1: От имени www-data
sudo -u www-data php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

# Вариант 2: С sudo (если текущий пользователь в группе www-data)
sudo php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

Если все еще ошибка, проверьте права:

```bash
# Добавить текущего пользователя в группу www-data
sudo usermod -a -G www-data $USER
# Выйти и войти снова, чтобы изменения вступили в силу

# Или сделать файл доступным для записи группой
sudo chmod 664 storage/logs/laravel.log
sudo chgrp www-data storage/logs/laravel.log
```

## Шаг 3: Запуск миграций

```bash
# От имени www-data
sudo -u www-data php artisan migrate
```

**Примечание:** Если возникла ошибка с tinker при проверке, см. `fix-tinker-permissions.sh`

## Шаг 4: Настройка конфигурации

### Обновить .env файл

Добавьте в `.env`:

```env
SANCTUM_STATEFUL_DOMAINS=its-infocenter.tech,localhost,127.0.0.1
SESSION_DOMAIN=.its-infocenter.tech
```

**Важно:** 
- `SANCTUM_STATEFUL_DOMAINS` - домены, для которых Sanctum будет использовать cookies
- `SESSION_DOMAIN` - домен для сессий (с точкой в начале для поддоменов)

### Обновить config/sanctum.php (если нужно)

```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    env('APP_URL') ? ','.parse_url(env('APP_URL'), PHP_URL_HOST) : ''
))),
```

## Шаг 5: Очистка кэша

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

## Шаг 6: Перезапуск сервисов

```bash
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx
```

## Проверка

После установки Sanctum:
- API запросы из браузера будут использовать cookies для аутентификации
- Работает как для веб-интерфейса, так и для мобильных приложений (с токенами)

## Готово!

Sanctum теперь настроен и готов к использованию.

