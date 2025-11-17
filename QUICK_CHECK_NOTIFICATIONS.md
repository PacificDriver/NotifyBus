# Быстрая проверка статуса уведомлений

## 📊 Самый быстрый способ - через SQL

### Подключитесь к БД:
```bash
psql -h localhost -U ваш_пользователь -d bus_notifications
```

### Затем выполните:

#### 1. Общая статистика (успешно/неудачно):
```sql
SELECT 
    channel,
    status,
    COUNT(*) as count
FROM notifications
GROUP BY channel, status
ORDER BY channel, status;
```

#### 2. Последние уведомления:
```sql
SELECT 
    id,
    channel,
    recipient,
    status,
    sent_at,
    failed_at,
    created_at
FROM notifications
ORDER BY created_at DESC
LIMIT 20;
```

#### 3. Только успешные Email:
```sql
SELECT recipient, sent_at, subject
FROM notifications
WHERE channel = 'email' AND status = 'sent'
ORDER BY sent_at DESC
LIMIT 10;
```

#### 4. Только успешные WhatsApp:
```sql
SELECT recipient, sent_at, delivered_at
FROM notifications
WHERE channel = 'whatsapp' AND status IN ('sent', 'delivered')
ORDER BY sent_at DESC
LIMIT 10;
```

#### 5. Только ошибки:
```sql
SELECT channel, recipient, error_message, failed_at
FROM notifications
WHERE status = 'failed'
ORDER BY failed_at DESC
LIMIT 10;
```

## 🔍 Через логи

### Успешные отправки:
```bash
# Email
grep "Email sent successfully\|Notification.*sent successfully.*email" storage/logs/laravel.log | tail -10

# WhatsApp
grep "WhatsApp.*sent successfully\|Notification.*sent successfully.*whatsapp" storage/logs/laravel.log | tail -10
```

### Ошибки:
```bash
grep "Failed to send\|notification.*failed" storage/logs/laravel.log | tail -10
```

## 🌐 Через API (в браузере)

Откройте консоль разработчика (F12) и выполните:

```javascript
// Общая статистика
fetch('/api/notifications')
  .then(r => r.json())
  .then(data => {
    const stats = {};
    data.data.data.forEach(n => {
      const key = `${n.channel}_${n.status}`;
      stats[key] = (stats[key] || 0) + 1;
    });
    console.table(stats);
  });

// Только успешные Email
fetch('/api/notifications?status=sent&channel=email')
  .then(r => r.json())
  .then(data => console.table(data.data.data));

// Только успешные WhatsApp
fetch('/api/notifications?status=sent&channel=whatsapp')
  .then(r => r.json())
  .then(data => console.table(data.data.data));

// Только ошибки
fetch('/api/notifications?status=failed')
  .then(r => r.json())
  .then(data => console.table(data.data.data));
```

## 📝 Статусы уведомлений

- ✅ **sent** - Успешно отправлено
- ✅ **delivered** - Доставлено (WhatsApp)
- ⏳ **pending** - Ожидает отправки
- ⏳ **queued** - В очереди
- ❌ **failed** - Ошибка отправки

## 💡 Важно

Если в логах нет записей "Email sent successfully" или "Notification X sent successfully", значит:
- Либо уведомления еще не отправлялись (статус = pending/queued)
- Либо произошла ошибка (статус = failed)
- Либо worker не обрабатывает очередь

Проверьте статус в БД - это самый надежный способ!

