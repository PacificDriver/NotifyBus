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

    'carrier_api' => [
        'url' => env('CARRIER_API_URL', 'http://rc.rfbus.ru:8086'),
        'key' => env('CARRIER_API_KEY'), // x-access-token для API Сахалинского перевозчика
        'timeout' => env('CARRIER_API_TIMEOUT', 30),
    ],

    'notification' => [
        'batch_size' => env('NOTIFICATION_BATCH_SIZE', 10),
        'delay_seconds' => env('NOTIFICATION_DELAY_SECONDS', 2),
    ],

];


