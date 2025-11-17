-- Быстрая проверка статуса уведомлений

-- 1. Общая статистика
SELECT 
    channel,
    status,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (PARTITION BY channel), 2) as percentage
FROM notifications
GROUP BY channel, status
ORDER BY channel, status;

-- 2. Последние уведомления (20 штук)
SELECT 
    id,
    channel,
    LEFT(recipient, 30) as recipient,
    status,
    CASE 
        WHEN sent_at IS NOT NULL THEN TO_CHAR(sent_at, 'DD.MM.YYYY HH24:MI:SS')
        WHEN failed_at IS NOT NULL THEN TO_CHAR(failed_at, 'DD.MM.YYYY HH24:MI:SS')
        ELSE '—'
    END as event_time,
    LEFT(error_message, 50) as error_preview,
    created_at
FROM notifications
ORDER BY created_at DESC
LIMIT 20;

-- 3. Статистика по задачам (последние 5 задач)
SELECT 
    nt.id as task_id,
    nt.title,
    nt.status as task_status,
    COUNT(n.id) as total,
    SUM(CASE WHEN n.status = 'sent' THEN 1 ELSE 0 END) as sent,
    SUM(CASE WHEN n.status = 'failed' THEN 1 ELSE 0 END) as failed,
    SUM(CASE WHEN n.status IN ('pending', 'queued') THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN n.channel = 'email' THEN 1 ELSE 0 END) as email_count,
    SUM(CASE WHEN n.channel = 'whatsapp' THEN 1 ELSE 0 END) as whatsapp_count,
    ROUND(SUM(CASE WHEN n.status = 'sent' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(n.id), 0), 2) as success_rate
FROM notification_tasks nt
LEFT JOIN notifications n ON n.notification_task_id = nt.id
GROUP BY nt.id, nt.title, nt.status
ORDER BY nt.created_at DESC
LIMIT 5;

-- 4. Успешно отправленные Email (последние 10)
SELECT 
    id,
    recipient,
    LEFT(subject, 40) as subject,
    TO_CHAR(sent_at, 'DD.MM.YYYY HH24:MI:SS') as sent_at
FROM notifications
WHERE channel = 'email' 
  AND status = 'sent'
ORDER BY sent_at DESC
LIMIT 10;

-- 5. Успешно отправленные WhatsApp (последние 10)
SELECT 
    id,
    recipient,
    TO_CHAR(sent_at, 'DD.MM.YYYY HH24:MI:SS') as sent_at,
    CASE 
        WHEN delivered_at IS NOT NULL THEN TO_CHAR(delivered_at, 'DD.MM.YYYY HH24:MI:SS')
        ELSE 'Не доставлено'
    END as delivered_at
FROM notifications
WHERE channel = 'whatsapp' 
  AND status IN ('sent', 'delivered')
ORDER BY sent_at DESC
LIMIT 10;

-- 6. Неудачные отправки (последние 10)
SELECT 
    id,
    channel,
    recipient,
    LEFT(error_message, 80) as error_message,
    retry_count,
    TO_CHAR(failed_at, 'DD.MM.YYYY HH24:MI:SS') as failed_at
FROM notifications
WHERE status = 'failed'
ORDER BY failed_at DESC
LIMIT 10;

-- 7. Ожидающие отправки
SELECT 
    id,
    channel,
    recipient,
    status,
    TO_CHAR(queued_at, 'DD.MM.YYYY HH24:MI:SS') as queued_at,
    TO_CHAR(created_at, 'DD.MM.YYYY HH24:MI:SS') as created_at
FROM notifications
WHERE status IN ('pending', 'queued')
ORDER BY created_at DESC;

