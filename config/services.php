<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'whatsapp' => [
        'api_url' => env('WHATSAPP_API_URL', 'https://api.wappi.pro'),
        'api_token' => env('WHATSAPP_API_TOKEN'),
        'profile_id' => env('WHATSAPP_PROFILE_ID'),
        'daily_limit' => env('WHATSAPP_DAILY_LIMIT', 1000),
        'use_async' => env('WHATSAPP_USE_ASYNC', true), // Использовать асинхронную отправку
        'webhook_secret' => env('WHATSAPP_WEBHOOK_SECRET'), // Секретный ключ для верификации webhook
    ],

    // Carrier API настройки теперь хранятся в базе данных (таблица settings)
    // Эти значения больше не используются, но оставлены для обратной совместимости
    'carrier_api' => [
        'url' => env('CARRIER_API_URL', 'http://rc.rfbus.ru:8086'), // УСТАРЕЛО: Используйте настройки из БД
        'key' => env('CARRIER_API_KEY'), // УСТАРЕЛО: Используйте настройки из БД
        'timeout' => env('CARRIER_API_TIMEOUT', 30), // УСТАРЕЛО: Используйте настройки из БД
    ],

    'notification' => [
        'batch_size' => env('NOTIFICATION_BATCH_SIZE', 10),
        'delay_seconds' => env('NOTIFICATION_DELAY_SECONDS', 2),
    ],

];


