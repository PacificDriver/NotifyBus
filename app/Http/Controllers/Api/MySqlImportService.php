<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportState;
use App\Models\Setting;
use App\Services\MySqlImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

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
        $validated = $request->validate([
            'only_new' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100000',
        ]);

        try {
            $result = $this->service->syncFromRemote([
                'only_new' => (bool) ($validated['only_new'] ?? true),
                'limit' => $validated['limit'] ?? null,
            ]);
            $importReport = $this->runImmediateImport();

            return response()->json([
                'success' => true,
                'message' => 'Синхронизация завершена.',
                'data' => array_merge($result, [
                    'import_report' => $importReport,
                ]),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
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
        $commandOptions = [
            '--since-id' => (int) ($options['since_id'] ?? 0),
        ];

        if (!empty($options['race_ids']) && is_array($options['race_ids'])) {
            $commandOptions['--race-id'] = array_values(array_unique($options['race_ids']));
        }

        try {
            // Сбрасываем состояние, чтобы импорт прошёл по всему буферу
            ImportState::where('key', 'pb_order_item')->delete();

            Log::info('Running immediate PbOrderItem import', [
                'options' => $commandOptions,
            ]);

            Artisan::call('import:pb-order-items', $commandOptions);

            return [
                'success' => true,
                'options' => $commandOptions,
                'output' => Artisan::output(),
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to run immediate PbOrderItem import', [
                'options' => $commandOptions,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'options' => $commandOptions,
                'error' => $e->getMessage(),
            ];
        }
    }
}


