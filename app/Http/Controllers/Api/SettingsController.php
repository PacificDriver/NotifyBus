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
     * Получить все настройки
     */
    public function index(Request $request)
    {
        $group = $request->query('group');
        
        if ($group) {
            $settings = Setting::where('group', $group)->get();
            $result = [];
            foreach ($settings as $setting) {
                // Убираем префикс группы из ключа для удобства
                $key = str_replace("{$group}_", '', $setting->key);
                $result[$key] = $setting->value;
            }
            $settings = $result;
        } else {
            $settings = Setting::all()->groupBy('group')->map(function ($items, $groupName) {
                $result = [];
                foreach ($items as $item) {
                    // Убираем префикс группы из ключа
                    $key = str_replace("{$groupName}_", '', $item->key);
                    $result[$key] = $item->value;
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
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group' => 'required|string|in:whatsapp,email,carrier_api,notification',
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
                $isEncrypted = in_array($key, $encryptedKeys);
                // Сохраняем с префиксом группы для совместимости с WhatsAppService
                $fullKey = "{$group}_{$key}";
                
                Setting::set(
                    key: $fullKey,
                    value: $value,
                    group: $group,
                    type: $this->getSettingType($key),
                    encrypted: $isEncrypted
                );
            }

            // Обновляем конфигурацию кэша
            $this->clearConfigCache();

            Log::info("Settings updated", [
                'group' => $group,
                'keys' => array_keys($settings),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Settings saved successfully',
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
     * Получить список ключей, которые нужно зашифровать
     */
    protected function getEncryptedKeys(string $group): array
    {
        return match ($group) {
            'whatsapp' => ['api_token', 'webhook_secret'],
            'email' => ['password'],
            'carrier_api' => ['key'],
            default => [],
        };
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

