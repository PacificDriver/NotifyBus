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
        {--dry-run : Выполнить без сохранения (для проверки)}';

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
        $this->info('Запуск импорта pb_order_item...');

        $options = [
            'since_id' => $this->option('since-id') ? (int) $this->option('since-id') : null,
            'chunk' => (int) $this->option('chunk'),
            'dry_run' => (bool) $this->option('dry-run'),
        ];

        $raceIds = $this->option('race-id');
        if (!empty($raceIds)) {
            $options['race_ids'] = $raceIds;
        }

        $stats = $importer->import($options);

        $this->table(
            ['Метрика', 'Значение'],
            collect($stats)->map(fn ($value, $key) => [$key, $value])
        );

        if ($options['dry_run'] ?? false) {
            $this->warn('Импорт выполнен в режиме dry-run. Изменения не сохранены.');
        } else {
            $this->info('Импорт завершён успешно.');
        }

        return Command::SUCCESS;
    }
}


