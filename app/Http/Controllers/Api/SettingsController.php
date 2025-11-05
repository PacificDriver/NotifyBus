<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\WhatsAppService;
use App\Services\EmailService;
use App\Services\CarrierApiService;

class SettingsController extends Controller
{
    /**
     * Получить статус сервисов
     */
    public function status()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'whatsapp' => $this->checkWhatsAppStatus(),
                'carrier_api' => $this->checkCarrierApiStatus(),
                'email' => $this->checkEmailStatus(),
            ],
        ]);
    }

    /**
     * Проверить статус WhatsApp API
     */
    protected function checkWhatsAppStatus(): array
    {
        $apiToken = $this->getSettingValue('whatsapp', 'api_token') ?? config('services.whatsapp.api_token');
        $profileId = $this->getSettingValue('whatsapp', 'profile_id') ?? config('services.whatsapp.profile_id');
        
        $isConfigured = !empty($apiToken) && !empty($profileId);
        
        return [
            'configured' => $isConfigured,
            'message' => $isConfigured ? 'Настроен' : 'Не настроен (требуется конфигурация)',
        ];
    }

    /**
     * Проверить статус Carrier API
     */
    protected function checkCarrierApiStatus(): array
    {
        // Берем из БД в первую очередь
        $apiKey = $this->getSettingValue('carrier_api', 'key');
        $apiUrl = $this->getSettingValue('carrier_api', 'url');
        
        // Fallback на config если в БД нет
        if (empty($apiKey)) {
            $apiKey = config('services.carrier_api.key');
        }
        if (empty($apiUrl)) {
            $apiUrl = config('services.carrier_api.url');
        }
        
        $isConfigured = !empty($apiKey) && !empty($apiUrl);
        
        return [
            'configured' => $isConfigured,
            'message' => $isConfigured ? 'Настроен' : 'Не настроен (требуется конфигурация в админ-панели)',
        ];
    }

    /**
     * Проверить статус Email
     */
    protected function checkEmailStatus(): array
    {
        $host = $this->getSettingValue('email', 'host') ?? config('mail.mailers.smtp.host');
        $username = $this->getSettingValue('email', 'username') ?? config('mail.mailers.smtp.username');
        
        $isConfigured = !empty($host) && !empty($username);
        
        return [
            'configured' => $isConfigured,
            'message' => $isConfigured ? 'Настроен' : 'Не настроен (требуется конфигурация)',
        ];
    }

    /**
     * Получить значение настройки из БД
     */
    protected function getSettingValue(string $group, string $key)
    {
        try {
            $fullKey = "{$group}_{$key}";
            return \App\Models\Setting::get($fullKey);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Получить все настройки
     * Для секретных полей возвращает маски для безопасности отображения
     */
    public function index(Request $request)
    {
        $group = $request->query('group');
        
        if ($group) {
            $settings = Setting::where('group', $group)->get();
            $result = [];
            $secretKeys = $this->getEncryptedKeys($group);
            
            foreach ($settings as $setting) {
                // Убираем префикс группы из ключа для удобства
                $key = str_replace("{$group}_", '', $setting->key);
                
                // Для секретных полей показываем маску для безопасности
                if (in_array($key, $secretKeys)) {
                    $realValue = $setting->value; // Расшифрованное значение из БД
                    $result[$key] = $this->maskValue($realValue);
                } else {
                    $result[$key] = $setting->value;
                }
            }
            $settings = $result;
        } else {
            $settings = Setting::all()->groupBy('group')->map(function ($items, $groupName) {
                $result = [];
                $secretKeys = $this->getEncryptedKeys($groupName);
                
                foreach ($items as $item) {
                    // Убираем префикс группы из ключа
                    $key = str_replace("{$groupName}_", '', $item->key);
                    
                    // Для секретных полей показываем маску
                    if (in_array($key, $secretKeys)) {
                        $realValue = $item->value; // Расшифрованное значение из БД
                        $result[$key] = $this->maskValue($realValue);
                    } else {
                        $result[$key] = $item->value;
                    }
                }
                return $result;
            })->toArray();
        }

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Сохранить настройки
     * Секретные поля сохраняются в БД с шифрованием
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group' => 'required|string|in:whatsapp,email,carrier_api,external_db,notification',
            'settings' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $group = $request->input('group');
        $settings = $request->input('settings');

        try {
            // Определяем, какие поля нужно зашифровать
            $encryptedKeys = $this->getEncryptedKeys($group);

            foreach ($settings as $key => $value) {
                $isSecret = in_array($key, $encryptedKeys);
                // Сохраняем с префиксом группы для совместимости с сервисами
                $fullKey = "{$group}_{$key}";
                
                // Пропускаем пустые значения
                if ($value === '' || $value === null) {
                    continue;
                }
                
                // Если значение уже замаскировано (начинается с ***), не меняем
                if ($isSecret && (str_starts_with($value, '***') || str_starts_with($value, 'tok***'))) {
                    // Это уже маска, не обновляем реальное значение
                    continue;
                }
                
                // Для секретных полей сохраняем реальное значение с шифрованием в БД
                if ($isSecret && !empty($value)) {
                    Setting::set(
                        key: $fullKey,
                        value: $value, // Реальное значение, будет зашифровано автоматически
                        group: $group,
                        type: $this->getSettingType($key),
                        encrypted: true // Шифруем секретные значения
                    );
                } else {
                    // Для несекретных полей сохраняем как есть
                    Setting::set(
                        key: $fullKey,
                        value: $value,
                        group: $group,
                        type: $this->getSettingType($key),
                        encrypted: false
                    );
                }
            }

            // Обновляем конфигурацию кэша
            $this->clearConfigCache();

            Log::info("Settings updated", [
                'group' => $group,
                'keys' => array_keys($settings),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Настройки успешно сохранены в базу данных!',
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to save settings", [
                'group' => $group,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save settings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Проверить подключение к WhatsApp API
     */
    public function testWhatsApp(Request $request)
    {
        try {
            // Сохраняем временные настройки из запроса
            $tempSettings = $request->input('settings', []);
            
            if (!empty($tempSettings)) {
                foreach ($tempSettings as $key => $value) {
                    config(["services.whatsapp.{$key}" => $value]);
                }
            }

            $whatsappService = app(WhatsAppService::class);
            $status = $whatsappService->checkProfileStatus();

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp API connection successful',
                'data' => $status,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp API connection failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Проверить подключение к Email
     */
    public function testEmail(Request $request)
    {
        try {
            $email = $request->input('test_email', config('mail.from.address'));
            
            if (empty($email)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Test email address is required',
                ], 400);
            }

            $emailService = app(EmailService::class);
            
            // Отправляем тестовое письмо
            $emailService->send(
                to: $email,
                subject: 'Тестовое письмо от системы уведомлений',
                body: 'Это тестовое письмо для проверки настроек SMTP.'
            );

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Email sending failed: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Проверить подключение к API Перевозчика
     */
    public function testCarrierApi(Request $request)
    {
        try {
            $settings = $request->input('settings', []);
            
            // Получаем URL и ключ из запроса
            $testUrl = $settings['url'] ?? '';
            $testKey = $settings['key'] ?? '';
            
            // Если ключ замаскирован или пустой, берём реальное значение из БД
            if (empty($testKey) || str_starts_with($testKey, '***') || str_starts_with($testKey, 'tok***')) {
                $testKey = $this->getSettingValue('carrier_api', 'key');
            }
            
            // Если URL пустой, берём из БД
            if (empty($testUrl)) {
                $testUrl = $this->getSettingValue('carrier_api', 'url');
            }
            
            // Fallback на config если в БД нет
            if (empty($testUrl)) {
                $testUrl = config('services.carrier_api.url');
            }
            if (empty($testKey)) {
                $testKey = config('services.carrier_api.key');
            }
            
            // Проверяем, что есть URL и ключ
            if (empty($testUrl) || empty($testKey)) {
                throw new \Exception('URL API и ключ доступа обязательны для проверки подключения. Пожалуйста, настройте их в разделе "API Перевозчика".');
            }
            
            Log::info('Testing Carrier API connection', [
                'test_url' => $testUrl,
                'key_present' => !empty($testKey),
                'key_length' => strlen($testKey),
            ]);
            
            // Выполняем тестовый запрос напрямую
            $response = \Illuminate\Support\Facades\Http::timeout(15)
                ->withHeaders([
                    'x-access-token' => $testKey,
                    'Accept' => 'application/json',
                ])
                ->get($testUrl . '/stations');
            
            Log::info('Carrier API test response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
            ]);
            
            if (!$response->successful()) {
                $errorMsg = 'Ошибка подключения к API Перевозчика. ';
                
                if ($response->status() === 401 || $response->status() === 403) {
                    $errorMsg .= 'Неверный ключ доступа (x-access-token).';
                } elseif ($response->status() === 404) {
                    $errorMsg .= 'Неверный URL API или endpoint не найден.';
                } elseif ($response->status() >= 500) {
                    $errorMsg .= 'Сервер API недоступен или вернул ошибку.';
                } else {
                    $errorMsg .= 'HTTP ' . $response->status() . ': ' . $response->body();
                }
                
                throw new \Exception($errorMsg);
            }
            
            $data = $response->json();
            $stationsCount = is_array($data) ? count($data) : 0;
            
            Log::info('Carrier API test successful', [
                'stations_count' => $stationsCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Подключение к API Перевозчика успешно! Получено станций: ' . $stationsCount,
                'stations_count' => $stationsCount,
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Carrier API connection failed', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Не удалось подключиться к серверу API. Проверьте правильность URL и доступность сервера.',
            ], 400);
            
        } catch (\Exception $e) {
            Log::error('Carrier API test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Получить список ключей, которые являются секретными (токены, пароли)
     */
    protected function getEncryptedKeys(string $group): array
    {
        return match ($group) {
            'whatsapp' => ['api_token', 'webhook_secret'],
            'email' => ['password'],
            'carrier_api' => ['key'],
            'external_db' => ['password'],
            default => [],
        };
    }

    /**
     * Получить имя переменной окружения для настройки
     */
    protected function getEnvKeyForSetting(string $group, string $key): string
    {
        $mapping = [
            'whatsapp' => [
                'api_token' => 'WAPPI_API_TOKEN',
                'api_url' => 'WAPPI_API_URL',
                'profile_id' => 'WAPPI_PROFILE_ID',
            ],
            'email' => [
                'password' => 'MAIL_PASSWORD',
                'username' => 'MAIL_USERNAME',
                'host' => 'MAIL_HOST',
                'port' => 'MAIL_PORT',
            ],
            'carrier_api' => [
                'key' => 'CARRIER_API_KEY',
                'url' => 'CARRIER_API_URL',
            ],
            'external_db' => [
                'password' => 'EXTERNAL_DB_PASSWORD',
                'username' => 'EXTERNAL_DB_USERNAME',
                'host' => 'EXTERNAL_DB_HOST',
                'database' => 'EXTERNAL_DB_DATABASE',
                'port' => 'EXTERNAL_DB_PORT',
            ],
        ];

        return $mapping[$group][$key] ?? strtoupper("{$group}_{$key}");
    }

    /**
     * Замаскировать секретное значение
     * Пример: "secret123" -> "***123"
     */
    protected function maskValue(string $value): string
    {
        if (strlen($value) <= 4) {
            return '***';
        }
        
        // Показываем последние 4 символа
        return '***' . substr($value, -4);
    }

    /**
     * Определить тип настройки
     */
    protected function getSettingType(string $key): string
    {
        if (str_contains($key, '_limit') || str_contains($key, '_count') || str_contains($key, 'timeout')) {
            return 'integer';
        }
        
        if (str_contains($key, '_enabled') || str_contains($key, 'use_')) {
            return 'boolean';
        }
        
        return 'string';
    }

    /**
     * Очистить кэш конфигурации
     */
    protected function clearConfigCache(): void
    {
        try {
            // Очищаем кэш конфигурации
            if (function_exists('exec')) {
                exec('php artisan config:clear', $output, $return);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to clear config cache", ['error' => $e->getMessage()]);
        }
    }
}

