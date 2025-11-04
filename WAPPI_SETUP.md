# 🔧 Настройка Wappi.pro для WhatsApp API

## Описание

Система интегрирована с Wappi.pro для отправки WhatsApp сообщений пассажирам об отмене рейсов.

## 📋 Требования

1. Аккаунт на Wappi.pro (https://wappi.pro)
2. API токен из личного кабинета
3. Profile ID (ID профиля WhatsApp)

## ⚙️ Настройка

### 1. Получение учетных данных

1. Зайдите в личный кабинет Wappi.pro: https://wappi.pro
2. Перейдите в раздел настроек профиля
3. Скопируйте:
   - **API Token** (токен авторизации)
   - **Profile ID** (ID вашего профиля WhatsApp)

### 2. Настройка переменных окружения

Добавьте в файл `.env`:

```env
# Wappi.pro WhatsApp API
WHATSAPP_API_URL=https://api.wappi.pro
WHATSAPP_API_TOKEN=your_api_token_here
WHATSAPP_PROFILE_ID=your_profile_id_here
WHATSAPP_DAILY_LIMIT=1000
WHATSAPP_USE_ASYNC=true
WHATSAPP_WEBHOOK_SECRET=your_webhook_secret_here
```

**Важно:** `WHATSAPP_WEBHOOK_SECRET` - это секретный ключ для защиты webhook endpoint от несанкционированных запросов. 
Используйте сложный случайный ключ (например, сгенерированный через `php artisan key:generate` или онлайн генератор).

### 3. Настройка Webhook URL

В личном кабинете Wappi.pro настройте webhook URL для получения статусов доставки:

```
https://yourdomain.com/api/webhooks/wappi
```

**Настройка токена для webhook:**
- В настройках webhook в личном кабинете Wappi.pro укажите токен в заголовке `X-Wappi-Token`
- Этот же токен должен быть указан в `.env` как `WHATSAPP_WEBHOOK_SECRET`
- Если Wappi.pro не поддерживает кастомные заголовки, можно передать токен в query параметре `?token=your_secret`

**Важно:** Webhook необходим для автоматического обновления статусов доставки сообщений. Защита токеном предотвращает поддельные запросы.

### 4. Применение миграций

Выполните миграцию для добавления полей внешних ID:

```bash
php artisan migrate
```

## 📡 Формат номеров телефонов

Номера телефонов автоматически нормализуются в формат Wappi.pro:
- Формат: `79959640099@c.us`
- Номер должен начинаться с `7` (код России)
- Примеры:
  - `+7 (995) 964-00-99` → `79959640099@c.us`
  - `89959640099` → `79959640099@c.us`
  - `9959640099` → `79959640099@c.us`

## 🔄 Типы отправки сообщений

### Синхронная отправка
- Выполняется сразу
- Возвращает `message_id` сразу после отправки
- Устанавливается через `WHATSAPP_USE_ASYNC=false`

### Асинхронная отправка (рекомендуется)
- Сообщение ставится в очередь Wappi
- Возвращает `task_id` для отслеживания
- Статус доставки приходит через webhook
- Устанавливается через `WHATSAPP_USE_ASYNC=true`

## 📊 Статусы доставки

Система автоматически обновляет статусы через webhook:

- `pending` - сообщение в очереди
- `delivered` - сообщение доставлено
- `read` - сообщение прочитано
- `undelivered` - не доставлено
- `error` - ошибка доставки
- `temporary ban` - временная блокировка

## 🔍 Проверка работы

### 1. Проверка статуса профиля

```php
$whatsappService = app(\App\Services\WhatsAppService::class);
$status = $whatsappService->checkProfileStatus();
```

### 2. Проверка логов

Логи отправки сохраняются в `storage/logs/laravel.log`:

```bash
tail -f storage/logs/laravel.log | grep WhatsApp
```

### 3. Проверка webhook

Webhook должен быть доступен по адресу:
```
POST /api/webhooks/wappi
```

## ⚠️ Важные ограничения

1. **Лимиты отправки**
   - Настройте `WHATSAPP_DAILY_LIMIT` в соответствии с вашим тарифом
   - Система автоматически отслеживает количество отправленных сообщений

2. **Антиспам система WhatsApp**
   - Не отправляйте массовые рассылки нежелательных сообщений
   - Персонализируйте сообщения
   - Рекомендуется отправлять не более 5-10 сообщений в минуту

3. **Статус профиля**
   - Система отслеживает статус профиля через webhook
   - При статусе `offline` отправка сообщений может быть приостановлена

## 📚 Дополнительная документация

- [Документация Wappi.pro API](https://wappi.pro/api-documentation)
- [Postman коллекция](https://wappi.pro/api-documentation) - примеры запросов

## 🐛 Решение проблем

### Проблема: Сообщения не отправляются

1. Проверьте правильность `WHATSAPP_API_TOKEN` и `WHATSAPP_PROFILE_ID`
2. Убедитесь, что профиль WhatsApp активен (статус `online`)
3. Проверьте логи: `storage/logs/laravel.log`

### Проблема: Webhook не работает

1. Убедитесь, что webhook URL доступен извне
2. Проверьте настройки webhook в личном кабинете Wappi.pro
3. Проверьте логи webhook запросов

### Проблема: Неверный формат номера

1. Убедитесь, что номер начинается с `7` (код России)
2. Проверьте нормализацию номера в логах

## 📝 Пример использования

```php
use App\Services\WhatsAppService;

$whatsappService = app(WhatsAppService::class);

try {
    $result = $whatsappService->send(
        to: '79959640099',
        message: 'Ваш рейс отменен. Обратитесь в кассу для возврата средств.'
    );
    
    if ($result['success']) {
        $messageId = $result['message_id'] ?? $result['task_id'];
        // Сообщение отправлено или поставлено в очередь
    }
} catch (\Exception $e) {
    // Обработка ошибки
    logger()->error('WhatsApp send failed', ['error' => $e->getMessage()]);
}
```

## 🔐 Безопасность

1. **Не коммитьте `.env` файл** в репозиторий
2. Храните API токены в безопасном месте
3. Используйте HTTPS для webhook URL
4. **Обязательно настройте `WHATSAPP_WEBHOOK_SECRET`** для защиты webhook endpoint
5. Используйте сложный случайный ключ для webhook secret (минимум 32 символа)
6. Webhook endpoint защищен middleware `VerifyWappiWebhook`, который проверяет токен в заголовке `X-Wappi-Token` или query параметре `token`

