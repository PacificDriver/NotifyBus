<?php

namespace App\Services;

use App\Models\ImportState;
use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class ProcessManagerService
{
    public function list(): array
    {
        $definitions = config('processes.processes', []);

        return collect($definitions)
            ->map(fn (array $definition, string $name) => $this->formatProcessPayload($name, $definition))
            ->values()
            ->all();
    }

    public function start(string $name): array
    {
        $definition = $this->resolveDefinition($name);

        return match ($definition['type']) {
            'supervisor' => $this->startSupervisorProcess($name, $definition),
            'artisan_once' => $this->startArtisanProcess($name, $definition),
            default => throw new RuntimeException("Unsupported process type: {$definition['type']}"),
        };
    }

    public function stop(string $name): array
    {
        $definition = $this->resolveDefinition($name);

        return match ($definition['type']) {
            'supervisor' => $this->stopSupervisorProcess($name, $definition),
            'artisan_once' => $this->stopArtisanProcess($name, $definition),
            default => throw new RuntimeException("Unsupported process type: {$definition['type']}"),
        };
    }

    public function restart(string $name): array
    {
        $definition = $this->resolveDefinition($name);

        return match ($definition['type']) {
            'supervisor' => $this->restartSupervisorProcess($name, $definition),
            'artisan_once' => $this->restartArtisanProcess($name, $definition),
            default => throw new RuntimeException("Unsupported process type: {$definition['type']}"),
        };
    }

    public function status(string $name): array
    {
        $definition = $this->resolveDefinition($name);

        return $this->formatProcessPayload($name, $definition);
    }

    public function tailLogs(string $name, ?int $lines = null): array
    {
        $definition = $this->resolveDefinition($name);
        $logFile = $definition['log_file'] ?? null;

        if (!$logFile) {
            return [
                'success' => false,
                'message' => 'Log file path is not configured for process: ' . $name,
                'data' => [],
            ];
        }

        if (!File::exists($logFile)) {
            File::ensureDirectoryExists(dirname($logFile));
            File::put($logFile, '');
        }

        $lines = $lines ?? (int) config('processes.default_log_lines', 200);

        return [
            'success' => true,
            'data' => [
                'log' => $this->tailFile($logFile, $lines),
                'path' => $logFile,
            ],
        ];
    }

    protected function formatProcessPayload(string $name, array $definition): array
    {
        $basePayload = [
            'name' => $name,
            'label' => $definition['label'] ?? Str::headline($name),
            'type' => $definition['type'] ?? 'unknown',
            'description' => $definition['description'] ?? '',
            'log_file' => $definition['log_file'] ?? null,
        ];

        return array_merge($basePayload, $this->resolveStatusData($name, $definition));
    }

    protected function resolveStatusData(string $name, array $definition): array
    {
        return match ($definition['type']) {
            'supervisor' => $this->supervisorStatus($definition),
            'artisan_once' => $this->artisanStatus($name, $definition),
            default => [
                'status' => 'unknown',
                'details' => null,
            ],
        };
    }

    protected function supervisorStatus(array $definition): array
    {
        $target = $definition['target'] ?? null;
        if (!$target) {
            throw new RuntimeException('Supervisor target is not configured.');
        }

        $binary = config('processes.supervisor_binary', 'supervisorctl');
        $process = $this->runShellCommand("{$binary} status {$target}");

        if ($process['exit_code'] !== 0) {
            return [
                'status' => 'error',
                'details' => trim($process['stderr']) ?: trim($process['stdout']),
            ];
        }

        $statusLine = trim($process['stdout']);

        $status = 'unknown';
        if (stripos($statusLine, 'RUNNING') !== false) {
            $status = 'running';
        } elseif (stripos($statusLine, 'STOPPED') !== false) {
            $status = 'stopped';
        } elseif (stripos($statusLine, 'STARTING') !== false) {
            $status = 'starting';
        } elseif (stripos($statusLine, 'BACKOFF') !== false) {
            $status = 'backoff';
        } elseif (stripos($statusLine, 'EXITED') !== false) {
            $status = 'exited';
        }

        return [
            'status' => $status,
            'details' => $statusLine,
        ];
    }

    protected function artisanStatus(string $name, array $definition): array
    {
        $state = ImportState::firstWhere('key', $this->stateKey($name));
        $value = $state?->value ?? [];

        $pid = Arr::get($value, 'pid');
        $stateStatus = Arr::get($value, 'status', 'idle');
        $running = $pid ? $this->isProcessRunning((int) $pid) : null;

        if ($stateStatus === 'running' && $pid && $running === false) {
            $stateStatus = 'completed';
            ImportState::updateOrCreate(
                ['key' => $this->stateKey($name)],
                [
                    'value' => array_merge($value, [
                        'status' => 'completed',
                        'finished_at' => Carbon::now()->toIso8601String(),
                        'last_message' => 'Процесс завершен.',
                    ]),
                ]
            );
        }

        if ($stateStatus === 'running' && $pid === null && Arr::get($value, 'finished_at')) {
            $stateStatus = 'completed';
        }

        return [
            'status' => $stateStatus,
            'details' => [
                'pid' => $pid,
                'started_at' => Arr::get($value, 'started_at'),
                'finished_at' => Arr::get($value, 'finished_at'),
                'last_message' => Arr::get($value, 'last_message'),
                'command' => Arr::get($value, 'command'),
                'interval_seconds' => $this->resolveIntervalSeconds($definition),
            ],
        ];
    }

    protected function startSupervisorProcess(string $name, array $definition): array
    {
        $target = $definition['target'] ?? null;
        if (!$target) {
            throw new RuntimeException("Supervisor target not configured for process {$name}");
        }

        $binary = config('processes.supervisor_binary', 'supervisorctl');
        $result = $this->runShellCommand("{$binary} start {$target}");

        if ($result['exit_code'] !== 0) {
            return [
                'success' => false,
                'message' => trim($result['stderr']) ?: 'Failed to start supervisor process.',
            ];
        }

        return [
            'success' => true,
            'message' => trim($result['stdout']) ?: 'Supervisor process started.',
        ];
    }

    protected function stopSupervisorProcess(string $name, array $definition): array
    {
        $target = $definition['target'] ?? null;
        if (!$target) {
            throw new RuntimeException("Supervisor target not configured for process {$name}");
        }

        $binary = config('processes.supervisor_binary', 'supervisorctl');
        $result = $this->runShellCommand("{$binary} stop {$target}");

        if ($result['exit_code'] !== 0) {
            return [
                'success' => false,
                'message' => trim($result['stderr']) ?: 'Failed to stop supervisor process.',
            ];
        }

        return [
            'success' => true,
            'message' => trim($result['stdout']) ?: 'Supervisor process stopped.',
        ];
    }

    protected function restartSupervisorProcess(string $name, array $definition): array
    {
        $target = $definition['target'] ?? null;
        if (!$target) {
            throw new RuntimeException("Supervisor target not configured for process {$name}");
        }

        $binary = config('processes.supervisor_binary', 'supervisorctl');
        $result = $this->runShellCommand("{$binary} restart {$target}");

        if ($result['exit_code'] !== 0) {
            return [
                'success' => false,
                'message' => trim($result['stderr']) ?: 'Failed to restart supervisor process.',
            ];
        }

        return [
            'success' => true,
            'message' => trim($result['stdout']) ?: 'Supervisor process restarted.',
        ];
    }

    protected function startArtisanProcess(string $name, array $definition): array
    {
        $stateKey = $this->stateKey($name);
        $existing = ImportState::firstWhere('key', $stateKey);
        $value = $existing?->value ?? [];

        if ($value && $this->isProcessRunning((int) Arr::get($value, 'pid'))) {
            return [
                'success' => false,
                'message' => 'Процесс уже запущен. Остановите его перед повторным запуском.',
            ];
        }

        $command = $definition['command'] ?? null;
        if (!$command) {
            throw new RuntimeException("Artisan command not configured for process {$name}");
        }

        $arguments = $this->interpolateArguments(
            Arr::get($definition, 'options.arguments', []),
            $definition
        );
        $commandLine = $this->buildArtisanCommandLine($command, $arguments);

        $logFile = $definition['log_file'] ?? storage_path('logs/laravel.log');
        File::ensureDirectoryExists(dirname($logFile));

        $this->appendLogLine($logFile, sprintf('Запуск команды: php artisan %s', $commandLine));

        $pid = $this->runArtisanInBackground($commandLine, $logFile);

        ImportState::updateOrCreate(
            ['key' => $stateKey],
            [
                'value' => [
                    'pid' => $pid,
                    'status' => 'running',
                    'started_at' => Carbon::now()->toIso8601String(),
                    'command' => $commandLine,
                    'last_message' => 'Процесс запущен.',
                ],
            ]
        );

        return [
            'success' => true,
            'message' => 'Процесс импорта запущен.',
            'pid' => $pid,
        ];
    }

    protected function stopArtisanProcess(string $name, array $definition): array
    {
        $stateKey = $this->stateKey($name);
        $state = ImportState::firstWhere('key', $stateKey);
        $value = $state?->value ?? [];
        $pid = Arr::get($value, 'pid');

        if (!$pid) {
            return [
                'success' => false,
                'message' => 'PID процесса не найден. Возможно, он уже завершился.',
            ];
        }

        if (!$this->isProcessRunning((int) $pid)) {
            $this->finishArtisanState($name, 'completed', 'Процесс уже завершен.', Arr::get($value, 'command'));

            return [
                'success' => false,
                'message' => 'Процесс уже завершен.',
            ];
        }

        $killed = $this->killProcess((int) $pid);

        $message = $killed ? 'Процесс остановлен.' : 'Не удалось остановить процесс.';
        $this->finishArtisanState($name, $killed ? 'stopped' : 'failed', $message, Arr::get($value, 'command'));

        return [
            'success' => $killed,
            'message' => $message,
        ];
    }

    protected function restartArtisanProcess(string $name, array $definition): array
    {
        $stop = $this->stopArtisanProcess($name, $definition);
        if (!$stop['success'] && !Str::contains(strtolower($stop['message'] ?? ''), 'уже заверш')) {
            return $stop;
        }

        return $this->startArtisanProcess($name, $definition);
    }

    protected function finishArtisanState(string $name, string $status, string $message, ?string $command = null): void
    {
        $logFile = Arr::get($this->resolveDefinition($name), 'log_file', storage_path('logs/laravel.log'));
        $this->appendLogLine($logFile, $message);

        ImportState::updateOrCreate(
            ['key' => $this->stateKey($name)],
            [
                'value' => [
                    'status' => $status,
                    'finished_at' => Carbon::now()->toIso8601String(),
                    'last_message' => $message,
                    'command' => $command,
                ],
            ]
        );
    }

    protected function runArtisanInBackground(string $commandLine, string $logFile): ?int
    {
        $phpBinary = config('processes.php_binary', env('PHP_BINARY_PATH', PHP_BINDIR . DIRECTORY_SEPARATOR . 'php'));
        if (!is_executable($phpBinary)) {
            $phpBinary = 'php';
        }
        $php = escapeshellarg($phpBinary);
        $base = escapeshellarg(base_path());
        $log = escapeshellarg($logFile);

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = "cd /d {$base} && {$php} artisan {$commandLine} >> {$log} 2>&1";
            $full = 'start "" /B cmd /c ' . $cmd;
            pclose(popen($full, 'r'));

            return $this->findWindowsProcessId($commandLine);
        }

        $cmd = "cd {$base} && {$php} artisan {$commandLine} >> {$log} 2>&1 & echo $!";
        $output = [];
        exec($cmd, $output);

        $pid = null;
        if (!empty($output)) {
            $pid = (int) trim(end($output));
        }

        return $pid ?: null;
    }

    protected function buildArtisanCommandLine(string $command, array $arguments = []): string
    {
        $parts = [$command];

        foreach ($arguments as $argument) {
            $parts[] = $argument;
        }

        return implode(' ', $parts);
    }

    protected function interpolateArguments(array $arguments, array $definition): array
    {
        if (empty($arguments)) {
            return $arguments;
        }

        $placeholders = [];

        if (Arr::has($definition, 'options.interval_setting')) {
            $interval = $this->resolveIntervalSeconds($definition);
            if ($interval !== null) {
                $placeholders['{interval}'] = $interval;
            }
        }

        if (empty($placeholders)) {
            return $arguments;
        }

        return array_map(
            fn ($argument) => is_string($argument)
                ? str_replace(array_keys($placeholders), array_values($placeholders), $argument)
                : $argument,
            $arguments
        );
    }

    protected function resolveIntervalSeconds(array $definition): ?int
    {
        $settingKey = Arr::get($definition, 'options.interval_setting');
        $default = Arr::get($definition, 'options.default_interval', 420);

        if (!$settingKey) {
            return $default;
        }

        $value = Setting::get($settingKey, $default);
        $value = is_numeric($value) ? (int) $value : $default;

        return max(60, $value);
    }

    protected function findWindowsProcessId(string $commandLine): ?int
    {
        $needle = Str::contains($commandLine, ' ') ? Str::before($commandLine, ' ') : $commandLine;
        $escaped = addslashes($needle);
        $wmic = 'wmic process where "CommandLine like \'%artisan ' . $escaped . '%\'" get ProcessId /VALUE';

        $output = [];
        @exec($wmic, $output);

        foreach ($output as $line) {
            $line = trim($line);
            if (Str::startsWith($line, 'ProcessId=')) {
                $pid = (int) Str::after($line, '=');
                if ($pid > 0) {
                    return $pid;
                }
            }
        }

        return null;
    }

    protected function isProcessRunning(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            exec('tasklist /FI "PID eq ' . $pid . '"', $output);

            return count($output) > 1;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        $output = [];
        exec('ps -p ' . $pid, $output);

        return count($output) > 1;
    }

    protected function killProcess(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            exec('taskkill /F /PID ' . $pid, $output, $code);

            return $code === 0;
        }

        if (function_exists('posix_kill')) {
            @posix_kill($pid, SIGTERM);
            usleep(200000);
            if ($this->isProcessRunning($pid)) {
                @posix_kill($pid, SIGKILL);
            }

            return !$this->isProcessRunning($pid);
        }

        exec('kill ' . $pid, $output, $code);

        return $code === 0;
    }

    protected function runShellCommand(string $command): array
    {
        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, base_path());

        if (!is_resource($process)) {
            return [
                'exit_code' => 1,
                'stdout' => '',
                'stderr' => 'Failed to open process',
            ];
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $exitCode = proc_close($process);

        return [
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    protected function tailFile(string $path, int $lines = 200): array
    {
        $lines = max(1, $lines);

        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();

        $start = max(0, $lastLine - $lines + 1);

        $result = [];
        for ($line = $start; $line <= $lastLine; $line++) {
            $file->seek($line);
            $result[] = rtrim($file->current(), "\r\n");
        }

        return $result;
    }

    protected function appendLogLine(string $logFile, string $message): void
    {
        File::append($logFile, sprintf("[%s] %s%s", Carbon::now()->format('Y-m-d H:i:s'), $message, PHP_EOL));
    }

    protected function stateKey(string $name): string
    {
        return 'process:' . $name;
    }

    protected function resolveDefinition(string $name): array
    {
        $definitions = config('processes.processes', []);

        if (!array_key_exists($name, $definitions)) {
            throw new RuntimeException("Process definition '{$name}' not found.");
        }

        return $definitions[$name];
    }
}


