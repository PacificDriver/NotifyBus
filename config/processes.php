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
    'processes' => [
        'notification_worker' => [
            'label' => 'Worker уведомлений',
            'type' => 'supervisor',
            'target' => env('NOTIFICATION_WORKER_TARGET', 'notifybus-worker:*'),
            'log_file' => storage_path('logs/worker.log'),
            'description' => 'Фоновая очередь отправки WhatsApp и Email уведомлений.',
            'options' => [
                'namespace' => 'notifybus-worker',
            ],
        ],
        'passenger_import' => [
            'label' => 'Импорт пассажиров',
            'type' => 'artisan_once',
            'command' => env('PASSENGER_IMPORT_COMMAND', 'import:pb-order-items'),
            'log_file' => storage_path('logs/import.log'),
            'description' => 'Импортирует записи pb_order_item и обновляет пассажиров.',
            'options' => [
                'arguments' => [],
            ],
        ],
    ],
];


