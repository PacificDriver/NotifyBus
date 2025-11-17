#!/bin/bash

# Скрипт для проверки статуса уведомлений

echo "=== Проверка статуса уведомлений ==="
echo ""

# Проверка через БД (PostgreSQL)
echo "1. Статистика из базы данных:"
echo "-----------------------------------"
psql -h localhost -U postgres -d bus_notifications -c "
SELECT 
    channel,
    status,
    COUNT(*) as count
FROM notifications
GROUP BY channel, status
ORDER BY channel, status;
"

echo ""
echo "2. Последние 10 уведомлений:"
echo "-----------------------------------"
psql -h localhost -U postgres -d bus_notifications -c "
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
LIMIT 10;
"

echo ""
echo "3. Неудачные отправки (последние 5):"
echo "-----------------------------------"
psql -h localhost -U postgres -d bus_notifications -c "
SELECT 
    id,
    channel,
    recipient,
    error_message,
    failed_at
FROM notifications
WHERE status = 'failed'
ORDER BY failed_at DESC
LIMIT 5;
"

echo ""
echo "=== Проверка логов ==="
echo ""

echo "4. Успешные отправки Email (последние 5):"
echo "-----------------------------------"
grep "Email sent successfully" storage/logs/laravel.log | tail -5

echo ""
echo "5. Успешные отправки WhatsApp (последние 5):"
echo "-----------------------------------"
grep "WhatsApp.*sent successfully" storage/logs/laravel.log | tail -5

echo ""
echo "6. Ошибки отправки (последние 5):"
echo "-----------------------------------"
grep "Failed to send\|notification.*failed" storage/logs/laravel.log | tail -5

