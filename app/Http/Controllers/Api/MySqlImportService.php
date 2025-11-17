<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportState;
use App\Models\Setting;
use App\Services\MySqlImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MySqlImportController extends Controller
{
    public function __construct(protected MySqlImportService $service)
    {
    }

    public function status()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'last_dump' => ImportState::firstWhere('key', 'mysql_dump_import')?->value,
                'last_sync' => ImportState::firstWhere('key', 'mysql_remote_sync')?->value,
                'settings' => [
                    'table' => Setting::get('mysql_bridge_table', Setting::get('importer_source_table', 'pb_order_item')),
                    'primary_key' => Setting::get('mysql_bridge_primary_key', 'ID'),
                    'sync_only_new' => Setting::get('mysql_bridge_sync_only_new', true),
                    'sync_interval_seconds' => Setting::get('mysql_bridge_sync_interval_seconds', 600),
                ],
            ],
        ]);
    }

    public function uploadDump(Request $request)
    {
        $validated = $request->validate([
            'dump' => 'required|file|mimes:sql,txt,gz|max:524288',
        ]);

        try {
            $result = $this->service->importDump($request->file('dump'), false);
            $importReport = $this->runImmediateImport();

            return response()->json([
                'success' => true,
                'message' => 'Дамп успешно импортирован.',
                'data' => array_merge($result, [
                    'import_report' => $importReport,
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('MySQL dump upload failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function testConnection(Request $request)
    {
        $validated = $request->validate([
            'config' => 'nullable|array',
        ]);

        try {
            $result = $this->service->testRemoteConnection($validated['config'] ?? []);

            return response()->json([
                'success' => true,
                'message' => 'Подключение успешно.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function sync(Request $request)
    {
        // Увеличиваем время выполнения для длительных операций синхронизации
        // Используем тот же подход, что и в команде
        set_time_limit(300); // 5 минут
        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M'); // Увеличиваем лимит памяти

        // Устанавливаем заголовки для предотвращения кэширования и буферизации
        if (!headers_sent()) {
            header('Content-Type: application/json');
            header('X-Accel-Buffering: no'); // Отключаем буферизацию в Nginx
        }

        $validated = $request->validate([
            'only_new' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100000',
        ]);

        try {
            // Используем тот же подход, что и в команде - передаем max_execution_time
            $result = $this->service->syncFromRemote([
                'only_new' => (bool) ($validated['only_new'] ?? true),
                'limit' => $validated['limit'] ?? null,
                'max_execution_time' => 280, // Немного меньше, чем set_time_limit, чтобы успеть вернуть ответ
            ]);
            
            // Запускаем импорт пассажиров только если были обработаны строки
            // Используем тот же подход, что и в команде
            $importReport = null;
            if ($result['rows_processed'] > 0) {
                try {
                    // Получаем последний обработанный ID перед импортом (как в команде)
                    $lastProcessedId = ImportState::firstWhere('key', 'pb_order_item')?->value['last_id'] ?? 0;
                    
                    // Импортируем только новые записи (после последнего обработанного ID)
                    $importReport = $this->runImmediateImport([
                        'since_id' => $lastProcessedId,
                    ]);
                } catch (\Throwable $importError) {
                    Log::error('Failed to run import after sync', [
                        'error' => $importError->getMessage(),
                        'trace' => $importError->getTraceAsString(),
                    ]);
                    // Не прерываем выполнение, просто логируем ошибку импорта
                    $importReport = [
                        'success' => false,
                        'error' => $importError->getMessage(),
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Синхронизация завершена.',
                'data' => array_merge($result, [
                    'import_report' => $importReport,
                ]),
            ], 200, [
                'Content-Type' => 'application/json',
                'X-Accel-Buffering' => 'no',
            ]);
        } catch (\Throwable $e) {
            Log::error('MySQL sync failed in controller', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Проверяем, не является ли это ошибкой таймаута
            $isTimeout = str_contains($e->getMessage(), 'Maximum execution time') 
                      || str_contains($e->getMessage(), 'execution time exceeded')
                      || str_contains($e->getMessage(), 'FatalError');

            $errorMessage = $isTimeout 
                ? 'Синхронизация превысила лимит времени выполнения. Для больших объемов данных рекомендуется использовать команду через терминал: php artisan mysql:sync --mode=full'
                : $e->getMessage();

            // Всегда возвращаем JSON, даже при ошибках
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error' => class_basename($e),
                'is_timeout' => $isTimeout,
            ], 500, [
                'Content-Type' => 'application/json',
                'X-Accel-Buffering' => 'no',
            ]);
        }
    }

    /**
     * Очистить локальную таблицу pb_order_item.
     */
    public function clear(): \Illuminate\Http\JsonResponse
    {
        try {
            $targetTable = Setting::get('importer_source_table', 'pb_order_item');
            
            if (!Schema::hasTable($targetTable)) {
                throw new \RuntimeException("Таблица '{$targetTable}' недоступна.");
            }

            // Очищаем таблицу
            DB::table($targetTable)->truncate();
            
            // Сбрасываем состояние импорта
            ImportState::where('key', 'pb_order_item')->delete();
            ImportState::where('key', 'mysql_remote_sync')->delete();

            Log::info('MySQL local table cleared', [
                'table' => $targetTable,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Локальная таблица очищена успешно. Теперь можно выполнить полную синхронизацию.',
                'data' => [
                    'table' => $targetTable,
                ],
            ], 200, [
                'Content-Type' => 'application/json',
            ]);
        } catch (\Throwable $e) {
            Log::error('MySQL clear failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => class_basename($e),
            ], 500, [
                'Content-Type' => 'application/json',
            ]);
        }
    }

    /**
     * Выполнить импорт пассажиров синхронно, чтобы данные стали доступны сразу.
     *
     * @param  array<string,mixed>  $options
     * @return array<string,mixed>
     */
    protected function runImmediateImport(array $options = []): array
    {
        $commandOptions = [];

        // Если указаны конкретные race_ids, используем их и игнорируем sinceId
        if (!empty($options['race_ids']) && is_array($options['race_ids'])) {
            $commandOptions['--race-id'] = array_values(array_unique($options['race_ids']));
            // При фильтре по race_ids импортируем все записи для этих рейсов
        } else {
            // Иначе используем sinceId (если не указан, будет использован последний обработанный ID)
            $sinceId = $options['since_id'] ?? null;
            if ($sinceId !== null) {
                $commandOptions['--since-id'] = (int) $sinceId;
            }
            // Если sinceId не указан, импортер использует последний обработанный ID из состояния
        }

        try {
            // НЕ сбрасываем состояние импорта - это позволяет импортировать только новые записи
            // Состояние сбрасывается только при полном импорте (full mode)
            
            Log::info('Running immediate PbOrderItem import', [
                'options' => $commandOptions,
            ]);

            Artisan::call('import:pb-order-items', $commandOptions);

            $output = Artisan::output();
            
            Log::info('PbOrderItem import completed', [
                'options' => $commandOptions,
                'output_length' => strlen($output),
            ]);

            return [
                'success' => true,
                'options' => $commandOptions,
                'output' => $output,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to run immediate PbOrderItem import', [
                'options' => $commandOptions,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'options' => $commandOptions,
                'error' => $e->getMessage(),
            ];
        }
    }
}


