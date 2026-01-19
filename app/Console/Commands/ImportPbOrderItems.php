<?php

namespace App\Console\Commands;

use App\Services\PbOrderItemImporter;
use Illuminate\Console\Command;

class ImportPbOrderItems extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:pb-order-items
        {--since-id= : Импортировать только записи с ID больше указанного}
        {--race-id=* : Ограничить импорт конкретными внешними ID рейсов}
        {--chunk=500 : Размер порции для обработки}
        {--dry-run : Выполнить без сохранения (для проверки)}
        {--watch : Запустить в режиме постоянного цикла (для фонового процесса)}
        {--interval=420 : Интервал между циклами в секундах при включенном режиме watch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Импортировать записи из pb_order_item (реплика из MySQL) в локальные станции, маршруты, рейсы и пассажиров';

    /**
     * Execute the console command.
     */
    public function handle(PbOrderItemImporter $importer): int
    {
        $options = [
            'since_id' => $this->option('since-id') ? (int) $this->option('since-id') : null,
            'chunk' => (int) $this->option('chunk'),
            'dry_run' => (bool) $this->option('dry-run'),
        ];

        $raceIds = $this->option('race-id');
        if (!empty($raceIds)) {
            $options['race_ids'] = $raceIds;
        }

        $watchMode = (bool) $this->option('watch');
        $interval = max(60, (int) $this->option('interval'));

        if ($watchMode && ($options['dry_run'] ?? false)) {
            $this->warn('Режим dry-run игнорируется при включённом watch, данные не будут сохраняться.');
        }

        return $watchMode
            ? $this->runWatchLoop($importer, $options, $interval)
            : $this->runSingleImport($importer, $options);
    }

    protected function runSingleImport(PbOrderItemImporter $importer, array $options): int
    {
        $this->info('Запуск одноразового импорта pb_order_item...');

        $stats = $importer->import($options);
        $this->renderStatsTable($stats);

        if ($options['dry_run'] ?? false) {
            $this->warn('Импорт выполнен в режиме dry-run. Изменения не сохранены.');
        } else {
            $this->info('Импорт pb_order_item завершён успешно.');
            
            // Загружаем пассажиров из Startport API для отмененных рейсов
            $this->info('Загрузка пассажиров из Startport API...');
            $startportStats = $importer->loadStartportPassengers();
            
            if ($startportStats['startport_trips_processed'] > 0) {
                $this->info('Загрузка из Startport завершена:');
                $this->renderStatsTable($startportStats);
            } else {
                $this->info('Startport API не настроен или нет отмененных рейсов.');
            }
        }

        return Command::SUCCESS;
    }

    protected function runWatchLoop(PbOrderItemImporter $importer, array $options, int $interval): int
    {
        $this->info("Режим непрерывного импорта включён. Интервал между циклами: {$interval} сек.");

        while (true) {
            $cycleStartedAt = now();

            try {
                $this->runSingleImport($importer, $options);
            } catch (\Throwable $e) {
                $this->error("Ошибка цикла импорта: {$e->getMessage()}");
            }

            $elapsed = $cycleStartedAt->diffInSeconds(now());
            $this->info("Цикл завершён за {$elapsed} сек. Пауза {$interval} сек...");

            sleep($interval);
        }
    }

    protected function renderStatsTable(array $stats): void
    {
        $this->table(
            ['Метрика', 'Значение'],
            collect($stats)->map(fn ($value, $key) => [$key, $value])
        );
    }
}


