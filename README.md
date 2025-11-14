## NotifyBus

### Управление процессами уведомлений

Админ‑панель включает раздел «Управление процессами», который позволяет запускать, останавливать и перезапускать ключевые фоновые задачи через UI, а также просматривать последние логи.

- Конфигурация доступна в `config/processes.php`. По умолчанию определены два процесса:
  - `notification_worker` — управляется через Supervisor (`notifybus-worker:*`) и отвечает за очередь отправки WhatsApp/Email уведомлений.
  - `passenger_import` — непрерывный процесс `php artisan import:pb-order-items --watch --interval={interval}`, автоматически повторяющий импорт (интервал задаётся в админке).
- При необходимости задайте переменные окружения:
  - `SUPERVISORCTL_PATH` — путь к `supervisorctl`, если утилита не в `$PATH`.
  - `NOTIFICATION_WORKER_TARGET` — имя программы Supervisor (например, `notifybus-worker:*`).
  - `PASSENGER_IMPORT_COMMAND` — альтернативная команда импорта (без параметров `--watch/--interval`).
- Логи процессов настраиваются по пути `log_file` (по умолчанию `storage/logs/worker.log` и `storage/logs/import.log`). UI показывает последние ~200 строк.
- Для работы кнопок с Supervisor убедитесь, что веб-приложение имеет права на выполнение `supervisorctl`.
- Интервал и таблица-источник импорта настраиваются в админке (`Настройки → 🚍 Импорт`). Значения сохраняются в БД (`importer_interval_seconds`, `importer_source_table`) и применяются при следующем запуске фонового процесса.

### Supervisor-мод

Если нужно, чтобы процесс автоматически поднимался после ребута сервера, достаточно:

1. Добавить программу в supervisor (пример: `/etc/supervisor/conf.d/notifybus-importer.conf`):
   ```
   [program:notifybus-importer]
   command=/usr/bin/php /var/www/notifybus/artisan import:pb-order-items --watch --interval=420
   directory=/var/www/notifybus
   autostart=true
   autorestart=true
   stderr_logfile=/var/www/notifybus/storage/logs/import-supervisor-error.log
   stdout_logfile=/var/www/notifybus/storage/logs/import-supervisor.log
   ```
2. В `config/processes.php` сменить тип на `supervisor` и указать `target` (например, `notifybus-importer`).
3. В админке останется тот же интерфейс: кнопки Start/Stop вызывают `supervisorctl start/stop notifybus-importer`, логи берутся из указанного файла.

> Подсказка: если используется Windows‑окружение для разработки, операции с Supervisor будут недоступны — в этом случае действия в UI вернут ошибку, но логи останутся доступными.

