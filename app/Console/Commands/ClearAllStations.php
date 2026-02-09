<?php

namespace App\Console\Commands;

use App\Models\Station;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ClearAllStations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stations:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Удалить все станции из базы данных';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Начинаем очистку станций...');
        
        try {
            $count = Station::count();
            
            if ($count === 0) {
                $this->info('В базе данных нет станций для удаления.');
                return Command::SUCCESS;
            }
            
            $this->warn("Будет удалено станций: {$count}");
            
            // Удаляем все станции
            Station::query()->delete();
            
            Log::info('All stations cleared', [
                'deleted_count' => $count,
            ]);
            
            $this->info("✅ Успешно удалено станций: {$count}");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Ошибка при очистке станций: ' . $e->getMessage());
            
            Log::error('Failed to clear stations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return Command::FAILURE;
        }
    }
}
