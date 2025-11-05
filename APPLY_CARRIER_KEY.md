# 🔑 Применение ключа API Перевозчика

## Способ 1: Через .env файл (рекомендуется)

### 1. Добавить ключ в .env

```bash
# Открыть .env файл
nano /var/www/html/NotifyBus/.env

# Добавить или изменить:
CARRIER_API_KEY=ваш_ключ_здесь
CARRIER_API_URL=http://rc.rfbus.ru:8086
CARRIER_API_TIMEOUT=30
```

### 2. Очистить кэш конфигурации

```bash
cd /var/www/html/NotifyBus

# Очистить кэш конфигурации (ВАЖНО!)
sudo -u www-data php artisan config:clear

# Или если вы в группе www-data:
php artisan config:clear
```

### 3. Проверить, что ключ применился

**Вариант 1: Через команду (без tinker)**

```bash
# Просто вывести значение конфигурации
sudo -u www-data php artisan tinker --execute="echo config('services.carrier_api.key');"
```

**Вариант 2: Исправить права для tinker (если нужно)**

Если возникла ошибка "Writing to directory /var/www/.config/psysh is not allowed":

```bash
sudo bash fix-tinker-permissions.sh
```

Затем:
```bash
sudo -u www-data php artisan tinker
```

В консоли tinker:
```php
config('services.carrier_api.key')
// Должен вернуть ваш ключ
exit
```

**Вариант 3: Проверить через админ-панель**

Просто перейдите в `/admin/settings` → вкладка "🚌 API Перевозчика" → кнопка "🔍 Проверить подключение"

## Способ 2: Через админ-панель (хранится в БД)

### 1. Войти в админ-панель

```
http://your-domain.com/admin/settings
```

### 2. Перейти на вкладку "🚌 API Перевозчика"

### 3. Ввести ключ в поле "API Key (x-access-token)"

### 4. Нажать "💾 Сохранить настройки"

Настройки сохранятся в базе данных и будут использоваться вместо .env.

## Какой способ использовать?

- **.env файл** - для первоначальной настройки и продакшена
- **Админ-панель** - для изменения настроек без доступа к серверу

## Важно!

После изменения `.env` **обязательно** выполните:
```bash
sudo -u www-data php artisan config:clear
```

Laravel кэширует конфигурацию, и без очистки кэша новые значения не применятся.

## Проверка работы

### Через админ-панель:

1. Перейдите в `/admin/settings`
2. Вкладка "🚌 API Перевозчика"
3. Нажмите "🔍 Проверить подключение"

### Через код:

```php
use App\Services\CarrierApiService;

$service = new CarrierApiService();
// Сервис автоматически использует ключ из config('services.carrier_api.key')
```

## Переменные окружения для Carrier API

```env
# URL API перевозчика
CARRIER_API_URL=http://rc.rfbus.ru:8086

# Ключ доступа (x-access-token)
CARRIER_API_KEY=ваш_ключ

# Таймаут запросов в секундах
CARRIER_API_TIMEOUT=30
```

