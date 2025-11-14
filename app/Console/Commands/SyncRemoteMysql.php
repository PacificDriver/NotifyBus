<?php

namespace App\Console\Commands;

use App\Services\MySqlImportService;
use Illuminate\Console\Command;

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
        $interval = max(60, (int) $this->option('interval'));

        if ($watch) {
            $this->info("Включен режим наблюдения. Интервал {$interval} сек.");

            while (true) {
                $this->runSyncCycle($service, $mode, $limit);
                $this->info("Пауза {$interval} сек...");
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
    }
}


