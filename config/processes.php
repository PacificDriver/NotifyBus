<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Supervisor binary path
    |--------------------------------------------------------------------------
    |
    | Allows overriding the supervisorctl binary if it lives in a non-standard
    | location. By default we assume it is available in PATH.
    |
    */
    'supervisor_binary' => env('SUPERVISORCTL_PATH', 'supervisorctl'),

    /*
    |--------------------------------------------------------------------------
    | Default amount of log lines to return when tailing process logs
    |--------------------------------------------------------------------------
    */
    'default_log_lines' => (int) env('PROCESS_LOG_LINES', 200),

    /*
    |--------------------------------------------------------------------------
    | Managed Processes
    |--------------------------------------------------------------------------
    |
    | Here we describe long-running jobs that the admin UI can control.
    | Each process definition supports:
    |  - label: Human-readable name
    |  - type:  supervisor|artisan_once
    |  - target / command: supervisor target name or artisan command signature
    |  - log_file: Path to log file that will be tailed in UI
    |  - description: Short hint for UI
    |  - options: Optional array with additional params per type
    |
    */
    'php_binary' => env('PHP_BINARY_PATH', PHP_BINDIR . DIRECTORY_SEPARATOR . 'php'),

    'processes' => [
        'notification_worker' => [
            'label' => 'Worker уведомлений',
            'type' => 'supervisor',
            'target' => env('NOTIFICATION_WORKER_TARGET', 'notifybus-worker'),
            'log_file' => storage_path('logs/worker.log'),
            'description' => 'Фоновая очередь отправки WhatsApp и Email уведомлений (управляется через Supervisor).',
        ],
        'passenger_import' => [
            'label' => 'Импорт пассажиров',
            'type' => 'supervisor',
            'target' => env('PASSENGER_IMPORT_TARGET', 'notifybus-importer'),
            'log_file' => storage_path('logs/import-supervisor.log'),
            'description' => 'Импортирует пассажиров из внешней таблицы (цикл под Supervisor).',
            'options' => [
                'interval_setting' => 'importer_interval_seconds',
                'default_interval' => (int) env('PASSENGER_IMPORT_INTERVAL', 420),
            ],
        ],
        'mysql_sync' => [
            'label' => 'Синхронизация MySQL',
            'type' => 'supervisor',
            'target' => env('MYSQL_SYNC_TARGET', 'notifybus-mysql-sync'),
            'log_file' => storage_path('logs/mysql-sync.log'),
            'description' => 'Поддерживает реплику pb_order_item из удалённой MySQL.',
            'options' => [
                'interval_setting' => 'mysql_bridge_sync_interval_seconds',
                'default_interval' => (int) env('MYSQL_SYNC_INTERVAL', 600),
            ],
        ],
    ],
];


