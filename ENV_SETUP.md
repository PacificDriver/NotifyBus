# 📝 Настройка переменных окружения

## Быстрый старт

1. Скопируйте файл `.env.example` в `.env`:
   ```bash
   cp .env.example .env
   ```

2. Сгенерируйте ключ приложения:
   ```bash
   php artisan key:generate
   ```

3. Заполните необходимые переменные в `.env`

## 📋 Обязательные переменные

### База данных (PostgreSQL)
```env
DB_DATABASE=bus_notifications
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

### WhatsApp API (Wappi.pro)
```env
WHATSAPP_API_TOKEN=your_api_token
WHATSAPP_PROFILE_ID=your_profile_id
WHATSAPP_WEBHOOK_SECRET=your_webhook_secret
```

### API Перевозчика
```env
CARRIER_API_KEY=your_api_key
```

### Email (SMTP)
```env
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
```

## 🔐 Безопасность

- **НИКОГДА** не коммитьте файл `.env` в репозиторий
- Используйте `.env.example` как шаблон
- В продакшене используйте сильные пароли и токены
- Регулярно обновляйте секретные ключи

## 📖 Подробное описание переменных

### APP_* - Настройки приложения
- `APP_NAME` - Название приложения
- `APP_ENV` - Окружение: `local`, `staging`, `production`
- `APP_KEY` - Ключ шифрования (генерируется автоматически)
- `APP_DEBUG` - Режим отладки: `true` для разработки, `false` для продакшена
- `APP_URL` - URL приложения

### DB_* - База данных PostgreSQL
- `DB_CONNECTION` - Тип БД (по умолчанию `pgsql`)
- `DB_HOST` - Хост БД
- `DB_PORT` - Порт (по умолчанию `5432`)
- `DB_DATABASE` - Имя базы данных
- `DB_USERNAME` - Имя пользователя
- `DB_PASSWORD` - Пароль

### REDIS_* - Redis
- `REDIS_HOST` - Хост Redis
- `REDIS_PORT` - Порт (по умолчанию `6379`)
- `REDIS_PASSWORD` - Пароль (если требуется)
- `REDIS_DB` - База данных для очередей
- `REDIS_CACHE_DB` - База данных для кэша

### WHATSAPP_* - Wappi.pro API
- `WHATSAPP_API_URL` - URL API (по умолчанию `https://api.wappi.pro`)
- `WHATSAPP_API_TOKEN` - Токен авторизации из личного кабинета
- `WHATSAPP_PROFILE_ID` - ID профиля WhatsApp
- `WHATSAPP_WEBHOOK_SECRET` - Секретный ключ для защиты webhook
- `WHATSAPP_DAILY_LIMIT` - Лимит сообщений в день
- `WHATSAPP_USE_ASYNC` - Использовать асинхронную отправку (`true`/`false`)

### CARRIER_API_* - API Перевозчика
- `CARRIER_API_URL` - URL API перевозчика
- `CARRIER_API_KEY` - Ключ доступа (x-access-token)
- `CARRIER_API_TIMEOUT` - Таймаут запросов в секундах

### NOTIFICATION_* - Настройки уведомлений
- `NOTIFICATION_BATCH_SIZE` - Размер пакета сообщений
- `NOTIFICATION_DELAY_SECONDS` - Задержка между пакетами

### MAIL_* - Настройки почты
- `MAIL_MAILER` - Драйвер: `smtp`, `log` (для разработки)
- `MAIL_HOST` - SMTP хост
- `MAIL_PORT` - SMTP порт
- `MAIL_USERNAME` - Логин SMTP
- `MAIL_PASSWORD` - Пароль или App Password
- `MAIL_ENCRYPTION` - Шифрование: `tls` или `ssl`
- `MAIL_FROM_ADDRESS` - Email отправителя
- `MAIL_FROM_NAME` - Имя отправителя

## 🔧 Примеры для разных окружений

### Локальная разработка
```env
APP_ENV=local
APP_DEBUG=true
MAIL_MAILER=log
QUEUE_CONNECTION=sync
```

### Продакшен
```env
APP_ENV=production
APP_DEBUG=false
MAIL_MAILER=smtp
QUEUE_CONNECTION=redis
```

