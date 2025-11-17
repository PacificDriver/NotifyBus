-- Проверить последние уведомления
SELECT 
    id,
    channel,
    recipient,
    status,
    sent_at,
    failed_at,
    error_message,
    created_at
FROM notifications
ORDER BY created_at DESC
LIMIT 20;

-- Статистика по последним задачам
SELECT 
    nt.id as task_id,
    nt.title,
    nt.status as task_status,
    COUNT(n.id) as total_notifications,
    SUM(CASE WHEN n.status = 'sent' THEN 1 ELSE 0 END) as sent_count,
    SUM(CASE WHEN n.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
    SUM(CASE WHEN n.status IN ('pending', 'queued') THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN n.channel = 'email' THEN 1 ELSE 0 END) as email_count,
    SUM(CASE WHEN n.channel = 'whatsapp' THEN 1 ELSE 0 END) as whatsapp_count
FROM notification_tasks nt
LEFT JOIN notifications n ON n.notification_task_id = nt.id
WHERE nt.id IN (9, 10)
GROUP BY nt.id, nt.title, nt.status
ORDER BY nt.created_at DESC;

-- Детали уведомлений для задач 9 и 10
SELECT 
    n.id,
    n.channel,
    n.recipient,
    n.status,
    n.sent_at,
    n.failed_at,
    n.error_message,
    n.created_at
FROM notifications n
WHERE n.notification_task_id IN (9, 10)
ORDER BY n.created_at DESC;

