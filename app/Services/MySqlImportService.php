<?php

namespace App\Services;

use App\Models\ImportState;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class MySqlImportService
{
    protected string $dumpStateKey = 'mysql_dump_import';
    protected string $syncStateKey = 'mysql_remote_sync';

    /**
     * Загрузить дамп MySQL и импортировать в локальную таблицу реплики.
     */
    public function importDump(UploadedFile $file, bool $onlyNew = true, ?string $targetTable = null): array
    {
        $targetTable = $targetTable ?: Setting::get('importer_source_table', 'pb_order_item');

        if (!$targetTable) {
            throw new RuntimeException('Не указана таблица для импорта.');
        }

        if ($file->getSize() > 512 * 1024 * 1024) {
            throw new RuntimeException('Размер файла превышает 512 МБ.');
        }

        $storedPath = $file->storeAs(
            path: 'imports/mysql',
            name: now()->format('Ymd_His') . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension()
        );

        $absolutePath = Storage::path($storedPath);
        $sqlPath = $this->prepareDumpFile($absolutePath);
        $statements = $this->extractInsertStatements($sqlPath, $targetTable, $onlyNew);

        DB::beginTransaction();
        $estimatedRows = 0;

        try {
            $this->disableForeignKeyChecks();

            if (!$onlyNew) {
                DB::table($targetTable)->truncate();
            }

            foreach ($statements as $statement) {
                DB::unprepared($statement);
                $estimatedRows += $this->estimateRowsInStatement($statement);
            }

            $this->enableForeignKeyChecks();
            DB::commit();
        } catch (\Throwable $e) {
            $this->enableForeignKeyChecks();
            DB::rollBack();
            Log::error('MySQL dump import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }

        ImportState::updateOrCreate(
            ['key' => $this->dumpStateKey],
            [
                'value' => [
                    'stored_path' => $storedPath,
                    'statements' => count($statements),
                    'estimated_rows' => $estimatedRows,
                    'target_table' => $targetTable,
                    'only_new' => $onlyNew,
                    'imported_at' => now()->toIso8601String(),
                ],
            ]
        );

        return [
            'stored_path' => $storedPath,
            'statements' => count($statements),
            'estimated_rows' => $estimatedRows,
            'target_table' => $targetTable,
            'only_new' => $onlyNew,
        ];
    }

    /**
     * Синхронизировать данные напрямую из удалённой MySQL.
     *
     * @param  array{
     *     only_new?: bool,
     *     limit?: int|null,
     *     target_table?: string|null,
     *     remote_table?: string|null,
     *     primary_key?: string|null,
     *     connection?: array<string,mixed>|null
     * }  $options
     */
    public function syncFromRemote(array $options = []): array
    {
        $onlyNew = Arr::get($options, 'only_new', Setting::get('mysql_bridge_sync_only_new', true));
        $targetTable = Arr::get($options, 'target_table') ?: Setting::get('importer_source_table', 'pb_order_item');
        $remoteTable = Arr::get($options, 'remote_table') ?: Setting::get('mysql_bridge_table', $targetTable);
        $primaryKey = Arr::get($options, 'primary_key') ?: Setting::get('mysql_bridge_primary_key', 'ID');
        $limit = Arr::get($options, 'limit');

        if (!$targetTable || !$remoteTable) {
            throw new RuntimeException('Не указаны таблицы для синхронизации.');
        }

        $connection = $this->configureRemoteConnection($options['connection'] ?? []);
        $connectionName = $connection['name'];

        $query = DB::connection($connectionName)
            ->table($remoteTable)
            ->orderBy($primaryKey);

        $sinceId = null;

        if ($onlyNew) {
            $sinceId = Arr::get($options, 'since_id', $this->getSyncState('last_remote_id'));
            if ($sinceId) {
                $query->where($primaryKey, '>', $sinceId);
            }
        } else {
            DB::table($targetTable)->truncate();
        }

        $chunkSize = 500;
        $processed = 0;
        $maxRemoteId = $sinceId;
        $stoppedEarly = false;

        $query->chunkById($chunkSize, function ($rows) use (
            &$processed,
            &$maxRemoteId,
            &$stoppedEarly,
            $primaryKey,
            $targetTable,
            $limit
        ) {
            if (empty($rows)) {
                return false;
            }

            $payloads = [];
            foreach ($rows as $row) {
                $data = (array) $row;
                $payloads[] = $data;
                $candidateId = Arr::get($data, $primaryKey);
                if ($candidateId !== null) {
                    $maxRemoteId = $maxRemoteId === null
                        ? (int) $candidateId
                        : max((int) $candidateId, $maxRemoteId);
                }
            }

            if (!empty($payloads)) {
                DB::table($targetTable)->upsert($payloads, [$primaryKey]);
                $processed += count($payloads);
            }

            if ($limit && $processed >= $limit) {
                $stoppedEarly = true;
                return false;
            }

            return true;
        }, $primaryKey);

        DB::purge($connectionName);

        $this->rememberSyncState([
            'last_remote_id' => $maxRemoteId,
            'rows_processed' => $processed,
            'only_new' => $onlyNew,
            'target_table' => $targetTable,
            'remote_table' => $remoteTable,
            'finished_at' => now()->toIso8601String(),
            'stopped_early' => $stoppedEarly,
        ]);

        return [
            'rows_processed' => $processed,
            'last_remote_id' => $maxRemoteId,
            'only_new' => $onlyNew,
            'target_table' => $targetTable,
            'remote_table' => $remoteTable,
            'stopped_early' => $stoppedEarly,
        ];
    }

    /**
     * Проверить подключение к удалённой MySQL и получить краткую сводку.
     */
    public function testRemoteConnection(array $overrides = []): array
    {
        $connection = $this->configureRemoteConnection($overrides);
        $connectionName = $connection['name'];
        $remoteTable = $connection['config']['table'] ?? Setting::get('mysql_bridge_table', 'pb_order_item');
        $primaryKey = $connection['config']['primary_key'] ?? Setting::get('mysql_bridge_primary_key', 'ID');

        try {
            $count = DB::connection($connectionName)->table($remoteTable)->count();
            $latest = DB::connection($connectionName)
                ->table($remoteTable)
                ->orderByDesc($primaryKey)
                ->first();
        } finally {
            DB::purge($connectionName);
        }

        return [
            'table' => $remoteTable,
            'rows' => $count,
            'latest_id' => $latest ? Arr::get((array) $latest, $primaryKey) : null,
        ];
    }

    /**
     * Распаковать gzip при необходимости.
     */
    protected function prepareDumpFile(string $path): string
    {
        if (Str::endsWith($path, '.gz')) {
            $targetPath = Str::beforeLast($path, '.gz');
            $input = gzopen($path, 'rb');
            $output = fopen($targetPath, 'wb');

            while (!gzeof($input)) {
                fwrite($output, gzread($input, 4096));
            }

            fclose($input);
            fclose($output);

            return $targetPath;
        }

        return $path;
    }

    /**
     * @return array<int,string>
     */
    protected function extractInsertStatements(string $path, string $targetTable, bool $onlyNew): array
    {
        $handle = fopen($path, 'rb');

        if (!$handle) {
            throw new RuntimeException('Не удалось открыть файл дампа.');
        }

        $statements = [];
        $buffer = '';

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                continue;
            }

            $buffer .= $line;

            if (!Str::endsWith(rtrim($line), ';')) {
                continue;
            }

            $statement = trim($buffer);
            $buffer = '';

            if (!str_starts_with(strtoupper($statement), 'INSERT')) {
                continue;
            }

            $normalized = $this->normalizeInsertStatement($statement, $targetTable, $onlyNew);

            if ($normalized) {
                $statements[] = $normalized;
            }
        }

        fclose($handle);

        if (empty($statements)) {
            throw new RuntimeException('В дампе не найдено INSERT выражений.');
        }

        return $statements;
    }

    protected function normalizeInsertStatement(string $statement, string $targetTable, bool $onlyNew): ?string
    {
        $pattern = '/INSERT\s+(?:IGNORE\s+)?INTO\s+`?([^\s`(]+)`?/i';

        if (!preg_match($pattern, $statement)) {
            return null;
        }

        $replacement = 'INSERT ' . ($onlyNew ? 'IGNORE ' : '') . 'INTO `' . $targetTable . '`';
        $statement = preg_replace($pattern, $replacement, $statement, 1);

        if (!Str::endsWith($statement, ';')) {
            $statement .= ';';
        }

        return $statement;
    }

    protected function estimateRowsInStatement(string $statement): int
    {
        $valuesSegment = Str::after($statement, 'VALUES');
        if (!$valuesSegment) {
            return 0;
        }

        return max(1, substr_count($valuesSegment, '),') + 1);
    }

    protected function disableForeignKeyChecks(): void
    {
        try {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }
        } catch (\Throwable $e) {
            Log::debug('Failed to disable FK checks', ['error' => $e->getMessage()]);
        }
    }

    protected function enableForeignKeyChecks(): void
    {
        try {
            if (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        } catch (\Throwable $e) {
            Log::debug('Failed to enable FK checks', ['error' => $e->getMessage()]);
        }
    }

    protected function configureRemoteConnection(array $overrides = []): array
    {
        $config = [
            'host' => Arr::get($overrides, 'host', Setting::get('mysql_bridge_host')),
            'port' => (int) (Arr::get($overrides, 'port', Setting::get('mysql_bridge_port', 3306)) ?: 3306),
            'database' => Arr::get($overrides, 'database', Setting::get('mysql_bridge_database')),
            'username' => Arr::get($overrides, 'username', Setting::get('mysql_bridge_username')),
            'password' => Arr::get($overrides, 'password', Setting::get('mysql_bridge_password')),
            'table' => Arr::get($overrides, 'table', Setting::get('mysql_bridge_table', 'pb_order_item')),
            'primary_key' => Arr::get($overrides, 'primary_key', Setting::get('mysql_bridge_primary_key', 'ID')),
        ];

        if (empty($config['host']) || empty($config['database']) || empty($config['username'])) {
            throw new RuntimeException('Настройки подключения к MySQL заданы не полностью.');
        }

        $connectionName = 'mysql_bridge_' . uniqid();

        Config::set("database.connections.{$connectionName}", [
            'driver' => 'mysql',
            'host' => $config['host'],
            'port' => $config['port'],
            'database' => $config['database'],
            'username' => $config['username'],
            'password' => $config['password'],
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
        ]);

        return [
            'name' => $connectionName,
            'config' => $config,
        ];
    }

    protected function rememberSyncState(array $payload): void
    {
        ImportState::updateOrCreate(
            ['key' => $this->syncStateKey],
            ['value' => $payload]
        );
    }

    protected function getSyncState(string $key, $default = null)
    {
        $state = ImportState::firstWhere('key', $this->syncStateKey);

        return Arr::get($state?->value ?? [], $key, $default);
    }
}


