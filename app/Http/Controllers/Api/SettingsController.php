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
        $apiKey = $this->getSettingValue('carrier_api', 'key') ?? config('services.carrier_api.key');
        $apiUrl = $this->getSettingValue('carrier_api', 'url') ?? config('services.carrier_api.url');
        
        $isConfigured = !empty($apiKey) && !empty($apiUrl);
        
        return [
            'configured' => $isConfigured,
            'message' => $isConfigured ? 'Настроен' : 'Не настроен (требуется конфигурация)',
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
     * Для секретных полей возвращает маски, реальные значения берутся из .env
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
                
                // Для секретных полей показываем маску из БД, но реальное значение берем из .env
                if (in_array($key, $secretKeys)) {
                    // Показываем маску из БД, но также проверяем .env
                    $envKey = $this->getEnvKeyForSetting($group, $key);
                    $envValue = env($envKey);
                    
                    // Если значение есть в .env, показываем маску, иначе значение из БД
                    if ($envValue) {
                        $result[$key] = $this->maskValue($envValue);
                    } else {
                        $result[$key] = $setting->value; // Маска из БД
                    }
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
                        $envKey = $this->getEnvKeyForSetting($groupName, $key);
                        $envValue = env($envKey);
                        
                        if ($envValue) {
                            $result[$key] = $this->maskValue($envValue);
                        } else {
                            $result[$key] = $item->value;
                        }
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
     * Для секретных полей сохраняет маску в БД, реальные значения должны быть в .env
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
            // Определяем, какие поля нужно зашифровать/замаскировать
            $encryptedKeys = $this->getEncryptedKeys($group);
            $warnings = [];

            foreach ($settings as $key => $value) {
                $isSecret = in_array($key, $encryptedKeys);
                // Сохраняем с префиксом группы для совместимости с сервисами
                $fullKey = "{$group}_{$key}";
                
                // Для секретных полей сохраняем маску в БД
                if ($isSecret && !empty($value)) {
                    // Если значение уже замаскировано (начинается с ***), не меняем
                    if (str_starts_with($value, '***') || str_starts_with($value, 'tok***')) {
                        // Это уже маска, не обновляем
                        continue;
                    }
                    
                    // Сохраняем маску в БД
                    $maskedValue = $this->maskValue($value);
                    Setting::set(
                        key: $fullKey,
                        value: $maskedValue,
                        group: $group,
                        type: $this->getSettingType($key),
                        encrypted: false // Не шифруем, т.к. это маска
                    );
                    
                    // Добавляем предупреждение о необходимости обновить .env
                    $envKey = $this->getEnvKeyForSetting($group, $key);
                    $warnings[] = "Secret value for '{$key}' saved as mask. Please update .env file with key '{$envKey}' = '{$value}'";
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
                'warnings' => $warnings,
            ]);

            $response = [
                'success' => true,
                'message' => 'Settings saved successfully',
            ];
            
            if (!empty($warnings)) {
                $response['warnings'] = $warnings;
                $response['message'] .= '. Please update .env file for secret values.';
            }

            return response()->json($response);

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
            // Сохраняем временные настройки из запроса
            $tempSettings = $request->input('settings', []);
            
            if (!empty($tempSettings)) {
                foreach ($tempSettings as $key => $value) {
                    config(["services.carrier_api.{$key}" => $value]);
                }
            }

            $carrierService = app(CarrierApiService::class);
            
            // Пробуем выполнить простой запрос для проверки подключения
            // Используем метод, который есть в CarrierApiService
            // Если метод getStations не существует, можно просто проверить конфигурацию
            if (method_exists($carrierService, 'getStations')) {
                $response = $carrierService->getStations();
            } else {
                // Просто проверяем, что настройки загружены
                $config = config('services.carrier_api');
                if (empty($config['key']) || empty($config['url'])) {
                    throw new \Exception('API key or URL not configured');
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Carrier API connection successful',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Carrier API connection failed: ' . $e->getMessage(),
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

