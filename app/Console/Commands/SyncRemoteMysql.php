<?php

namespace App\Console\Commands;

use App\Services\MySqlImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class SyncRemoteMysql extends Command
{
    protected $signature = 'mysql:sync
        {--mode=new : Режим синхронизации: new или full}
        {--limit= : Максимум строк за один цикл}
        {--watch : Непрерывный режим с интервалами}
        {--interval=600 : Интервал между циклами в секундах в режиме watch}';

    protected $description = 'Синхронизирует таблицу pb_order_item с удалённой MySQL';

    public function handle(MySqlImportService $service): int
    {
        $mode = $this->option('mode') === 'full' ? 'full' : 'new';
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $watch = (bool) $this->option('watch');
        
        // Читаем интервал из настроек БД, если они есть
        $intervalFromDb = \App\Models\Setting::get('mysql_bridge_sync_interval_seconds');
        $intervalFromOption = (int) $this->option('interval');
        
        // Приоритет: настройки из БД > параметр командной строки > значение по умолчанию (600)
        if ($intervalFromDb !== null) {
            $interval = max(60, (int) $intervalFromDb);
            $this->info("Используется интервал из настроек БД: {$interval} сек.");
        } else {
            $interval = max(60, $intervalFromOption ?: 600);
            $this->info("Используется интервал из параметра командной строки: {$interval} сек.");
        }

        if ($watch) {
            $this->info("Включен режим наблюдения. Интервал {$interval} сек.");

            $previousInterval = $interval;
            
            while (true) {
                $this->runSyncCycle($service, $mode, $limit);
                
                // Перечитываем интервал из БД перед каждым циклом
                // Это позволяет менять интервал без перезапуска supervisor
                $newInterval = \App\Models\Setting::get('mysql_bridge_sync_interval_seconds');
                if ($newInterval !== null) {
                    $newInterval = max(60, (int) $newInterval);
                    
                    // Логируем изменение интервала, если оно произошло
                    if ($newInterval !== $previousInterval) {
                        $this->info("🔄 Интервал синхронизации изменен: {$previousInterval} сек → {$newInterval} сек");
                        Log::info('MySQL sync interval changed', [
                            'old_interval' => $previousInterval,
                            'new_interval' => $newInterval,
                        ]);
                        $previousInterval = $newInterval;
                    }
                    
                    $interval = $newInterval;
                }
                
                $this->info("⏸ Пауза {$interval} сек до следующей синхронизации...");
                sleep($interval);
            }
        }

        $this->runSyncCycle($service, $mode, $limit);

        return Command::SUCCESS;
    }

    protected function runSyncCycle(MySqlImportService $service, string $mode, ?int $limit = null): void
    {
        $onlyNew = $mode !== 'full';

        $this->info('Старт синхронизации MySQL (' . ($onlyNew ? 'только новые' : 'полный импорт') . ')');

        $result = $service->syncFromRemote([
            'only_new' => $onlyNew,
            'limit' => $limit,
        ]);

        $this->table(
            ['Метрика', 'Значение'],
            [
                ['rows_processed', $result['rows_processed']],
                ['last_remote_id', $result['last_remote_id'] ?? '—'],
                ['stopped_early', $result['stopped_early'] ? 'yes' : 'no'],
            ]
        );

        $this->info('Синхронизация завершена.');

        // Запускаем импорт пассажиров из pb_order_item в локальные сущности
        // Импортируем только новые записи (те, что были синхронизированы)
        // Не сбрасываем состояние импорта, чтобы не импортировать все записи заново
        if ($result['rows_processed'] > 0) {
            $this->info('Запуск импорта пассажиров из pb_order_item...');
            
            try {
                // Получаем последний обработанный ID перед импортом
                $lastProcessedId = \App\Models\ImportState::firstWhere('key', 'pb_order_item')?->value['last_id'] ?? 0;
                
                // Импортируем только новые записи (после последнего обработанного ID)
                // Это позволяет импортировать только те записи, которые были синхронизированы
                Artisan::call('import:pb-order-items', [
                    '--since-id' => $lastProcessedId,
                ]);
                
                $importOutput = Artisan::output();
                if (!empty(trim($importOutput))) {
                    $this->line($importOutput);
                }
                
                $this->info('Импорт пассажиров завершён.');
            } catch (\Throwable $e) {
                Log::error('Failed to run passenger import after MySQL sync', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error('Ошибка при импорте пассажиров: ' . $e->getMessage());
            }
        } else {
            $this->info('Нет новых данных для импорта.');
        }
    }
}


