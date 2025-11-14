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
        // Берем ТОЛЬКО из БД, без fallback на config/env
        $apiKey = $this->getSettingValue('carrier_api', 'key');
        $apiUrl = $this->getSettingValue('carrier_api', 'url');
        
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
            'group' => 'required|string|in:whatsapp,email,carrier_api,external_db,notification,importer',
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
            $settings = $request->input('settings', []);
            
            Log::debug('WhatsApp test request received', [
                'settings_keys' => array_keys($settings),
                'settings_has_api_token' => isset($settings['api_token']),
                'settings_has_profile_id' => isset($settings['profile_id']),
            ]);
            
            // Получаем настройки из запроса или из БД
            // По умолчанию используем https://wappi.pro (не api.wappi.pro) согласно документации
            $testApiUrl = $settings['api_url'] ?? $this->getSettingValue('whatsapp', 'api_url') ?? 'https://wappi.pro';
            $testApiToken = $settings['api_token'] ?? '';
            $testProfileId = $settings['profile_id'] ?? '';
            
            // Если токен замаскирован или пустой, берём реальное значение из БД
            if (empty($testApiToken) || str_starts_with($testApiToken, '***') || str_starts_with($testApiToken, 'tok***')) {
                $testApiToken = $this->getSettingValue('whatsapp', 'api_token');
                Log::debug('Using API token from DB', ['has_token' => !empty($testApiToken)]);
            }
            
            // Если profile_id пустой, берём из БД
            if (empty($testProfileId)) {
                $testProfileId = $this->getSettingValue('whatsapp', 'profile_id');
                Log::debug('Using profile_id from DB', ['has_profile_id' => !empty($testProfileId)]);
            }
            
            // Проверяем, что есть токен и profile_id
            if (empty($testApiToken)) {
                throw new \Exception('API токен не указан. Пожалуйста, укажите токен в поле "API Token" выше и нажмите "Сохранить настройки" перед проверкой подключения.');
            }
            
            if (empty($testProfileId)) {
                throw new \Exception('Profile ID не указан. Пожалуйста, укажите Profile ID в поле "Profile ID" выше и нажмите "Сохранить настройки" перед проверкой подключения.');
            }
            
            Log::info('Testing WhatsApp API connection', [
                'api_url' => $testApiUrl,
                'profile_id' => $testProfileId,
                'has_token' => !empty($testApiToken),
                'token_length' => strlen($testApiToken ?? ''),
            ]);
            
            // Убираем префикс Bearer если есть
            $authToken = str_starts_with($testApiToken, 'Bearer ') 
                ? substr($testApiToken, 7) 
                : $testApiToken;
            
            // Используем правильные endpoint'ы из документации Wappi.pro
            // Для проверки подключения лучше использовать /api/sync/get/status - получить статус и настройки профиля
            $endpoints = [
                '/api/sync/get/status', // Получить статус и настройки профиля (лучший для проверки)
                '/api/proxy/get', // Альтернативный вариант из документации
            ];
            
            $response = null;
            $lastError = null;
            $triedUrl = null;
            
            foreach ($endpoints as $endpoint) {
                // Заменяем {profile_id} на реальное значение для path параметров
                $endpoint = str_replace('{profile_id}', $testProfileId, $endpoint);
                $url = rtrim($testApiUrl, '/') . $endpoint;
                $triedUrl = $url;
                
                Log::debug('Trying WhatsApp API endpoint', [
                    'url' => $url,
                    'profile_id' => $testProfileId,
                    'auth_token_length' => strlen($authToken),
                ]);
                
                try {
                    // Вариант 1: GET с profile_id в query параметре
                    $response = \Illuminate\Support\Facades\Http::timeout(30)
                        ->connectTimeout(10)
                        ->withoutVerifying()
                        ->withHeaders([
                            'Authorization' => $authToken,
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ])
                        ->get($url, [
                            'profile_id' => $testProfileId,
                        ]);
                    
                    if ($response->successful()) {
                        Log::info('Successful WhatsApp API request (GET with query)', [
                            'url' => $url,
                            'status' => $response->status(),
                        ]);
                        break;
                    }
                    
                    // Вариант 2: GET без параметров (если profile_id уже в path)
                    if (str_contains($endpoint, '{profile_id}') || str_contains($endpoint, $testProfileId)) {
                        $response = \Illuminate\Support\Facades\Http::timeout(30)
                            ->connectTimeout(10)
                            ->withoutVerifying()
                            ->withHeaders([
                                'Authorization' => $authToken,
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                            ])
                            ->get($url);
                        
                        if ($response->successful()) {
                            Log::info('Successful WhatsApp API request (GET with path param)', [
                                'url' => $url,
                                'status' => $response->status(),
                            ]);
                            break;
                        }
                    }
                    
                    // Вариант 3: POST с profile_id в теле запроса
                    if ($response->status() === 404) {
                        $response = \Illuminate\Support\Facades\Http::timeout(30)
                            ->connectTimeout(10)
                            ->withoutVerifying()
                            ->withHeaders([
                                'Authorization' => $authToken,
                                'Content-Type' => 'application/json',
                                'Accept' => 'application/json',
                            ])
                            ->post($url, [
                                'profile_id' => $testProfileId,
                            ]);
                        
                        if ($response->successful()) {
                            Log::info('Successful WhatsApp API request (POST)', [
                                'url' => $url,
                                'status' => $response->status(),
                            ]);
                            break;
                        }
                    }
                    
                    $lastError = "HTTP {$response->status()}: " . substr($response->body(), 0, 200);
                    
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    Log::debug('Endpoint failed', [
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }
            
            // Если все endpoint'ы не сработали, пробуем проверить документацию FastAPI
            if (!$response || !$response->successful()) {
                // Пробуем получить документацию FastAPI (обычно /docs или /redoc)
                try {
                    $docsUrl = rtrim($testApiUrl, '/') . '/docs';
                    $docsResponse = \Illuminate\Support\Facades\Http::timeout(10)
                        ->connectTimeout(5)
                        ->withoutVerifying()
                        ->get($docsUrl);
                    
                    if ($docsResponse->successful()) {
                        Log::info('FastAPI docs available', ['url' => $docsUrl]);
                    }
                } catch (\Exception $e) {
                    // Игнорируем ошибки при проверке документации
                }
            }
            
            if (!$response || !$response->successful()) {
                // Если получили 404, значит API доступен, но endpoint не найден
                // Это может быть нормально, если endpoint изменился или используется другой формат
                if ($response && $response->status() === 404) {
                    // Проверяем, может быть API просто использует другой endpoint
                    // В этом случае считаем, что подключение к API работает, но endpoint нужно уточнить
                    throw new \Exception(
                        "API Wappi.pro доступен, но endpoint для проверки статуса профиля не найден.\n\n" .
                        "Проверенные endpoint'ы:\n" . 
                        implode("\n", array_map(function($ep) use ($testApiUrl) {
                            return "- " . rtrim($testApiUrl, '/') . $ep;
                        }, $endpoints)) . 
                        "\n\nПожалуйста, проверьте документацию Wappi.pro для актуального endpoint'а.\n" .
                        "Если подключение работает (можно отправлять сообщения), это нормально."
                    );
                }
                
                // Если 401/403 - проблема с авторизацией
                if ($response && ($response->status() === 401 || $response->status() === 403)) {
                    throw new \Exception("Ошибка авторизации. Проверьте правильность API токена.");
                }
                
                // Другие ошибки
                throw new \Exception(
                    "Не удалось подключиться к WhatsApp API.\n\n" .
                    "Последняя ошибка: " . ($lastError ?? 'Неизвестная ошибка') . "\n\n" .
                    "Проверьте:\n" .
                    "1. Правильность URL API (должен быть https://api.wappi.pro)\n" .
                    "2. Правильность API токена\n" .
                    "3. Правильность Profile ID\n" .
                    "4. Доступность API Wappi.pro"
                );
            }
            
            $responseBody = $response->body();
            $responseData = $response->json();
            
            Log::info('WhatsApp API test response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'response_body_preview' => substr($responseBody, 0, 500),
            ]);
            
            if (!$response->successful()) {
                $errorMsg = 'Ошибка подключения к WhatsApp API. ';
                
                if ($response->status() === 401 || $response->status() === 403) {
                    $errorMsg .= 'Неверный токен авторизации. Проверьте правильность API токена в настройках.';
                } elseif ($response->status() === 400) {
                    // Ошибка 400 - Bad Request
                    $errorData = $responseData ?? [];
                    $errorMessage = $errorData['message'] ?? $errorData['error'] ?? null;
                    
                    if ($errorMessage) {
                        $errorMsg .= $errorMessage;
                    } else {
                        // Проверяем, что может быть не так
                        if (empty($testProfileId)) {
                            $errorMsg .= 'Неверный запрос: Profile ID не указан или пустой.';
                        } elseif (empty($authToken)) {
                            $errorMsg .= 'Неверный запрос: API токен не указан или пустой.';
                        } else {
                            $errorMsg .= 'Неверный запрос. Проверьте правильность URL API, токена и Profile ID. Ответ сервера: ' . substr($responseBody, 0, 200);
                        }
                    }
                } elseif ($response->status() === 404) {
                    $errorMsg .= 'Endpoint не найден. Проверьте правильность URL API: ' . $testApiUrl;
                } else {
                    $errorMsg .= 'HTTP ' . $response->status() . ': ' . substr($responseBody, 0, 200);
                }
                
                Log::warning('WhatsApp API test failed with error response', [
                    'status' => $response->status(),
                    'error_message' => $errorMsg,
                    'response_body' => $responseBody,
                ]);
                
                throw new \Exception($errorMsg);
            }

            return response()->json([
                'success' => true,
                'message' => 'Подключение к WhatsApp API успешно!',
                'data' => $responseData,
            ]);

        } catch (\Exception $e) {
            Log::error('WhatsApp API test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Отправить тестовое сообщение через WhatsApp API
     */
    public function testSendWhatsApp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone' => 'required|string',
                'message' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Не указан номер телефона или текст сообщения',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $phone = $request->input('phone');
            $message = $request->input('message');
            $settings = $request->input('settings', []);
            
            // Получаем настройки из запроса или из БД
            $testApiUrl = $settings['api_url'] ?? $this->getSettingValue('whatsapp', 'api_url') ?? 'https://wappi.pro';
            $testApiToken = $settings['api_token'] ?? '';
            $testProfileId = $settings['profile_id'] ?? '';
            
            // Если токен замаскирован или пустой, берём реальное значение из БД
            if (empty($testApiToken) || str_starts_with($testApiToken, '***') || str_starts_with($testApiToken, 'tok***')) {
                $testApiToken = $this->getSettingValue('whatsapp', 'api_token');
            }
            
            // Если profile_id пустой, берём из БД
            if (empty($testProfileId)) {
                $testProfileId = $this->getSettingValue('whatsapp', 'profile_id');
            }
            
            // Проверяем, что есть токен и profile_id
            if (empty($testApiToken)) {
                throw new \Exception('API токен не указан. Пожалуйста, укажите токен в поле "API Token" выше и нажмите "Сохранить настройки" перед отправкой сообщения.');
            }
            
            if (empty($testProfileId)) {
                throw new \Exception('Profile ID не указан. Пожалуйста, укажите Profile ID в поле "Profile ID" выше и нажмите "Сохранить настройки" перед отправкой сообщения.');
            }
            
            // Нормализуем номер телефона
            $phone = preg_replace('/[^0-9]/', '', $phone);
            if (!str_starts_with($phone, '7') && strlen($phone) === 10) {
                $phone = '7' . $phone;
            }
            if (!str_starts_with($phone, '7')) {
                throw new \Exception('Неверный формат номера телефона. Номер должен начинаться с 7 (например: 79959640099)');
            }
            
            // Формируем номер для Wappi.pro
            // Попробуем разные форматы, так как API может требовать разный формат
            // Сначала пробуем без @c.us (просто номер)
            // Если не сработает, попробуем с @c.us
            $to = $phone; // Пробуем сначала без @c.us
            
            Log::info('Sending test WhatsApp message', [
                'api_url' => $testApiUrl,
                'profile_id' => $testProfileId,
                'to' => $to,
                'has_token' => !empty($testApiToken),
            ]);
            
            // Отправляем сообщение через WhatsApp API
            $endpoint = '/api/sync/message/send';
            $url = rtrim($testApiUrl, '/') . $endpoint;
            $fullUrl = $url . '?profile_id=' . urlencode($testProfileId);
            
            // Убираем префикс Bearer если есть
            $authToken = str_starts_with($testApiToken, 'Bearer ') 
                ? substr($testApiToken, 7) 
                : $testApiToken;
            
            Log::debug('Sending WhatsApp test message request', [
                'url' => $fullUrl,
                'to' => $to,
                'message_length' => strlen($message),
                'profile_id' => $testProfileId,
                'has_token' => !empty($authToken),
            ]);
            
            // Согласно документации Wappi.pro: поле называется "recipient", не "to"
            // Формат: {"recipient": "79115576362", "body": "Тестовое сообщение"}
            // Номер должен быть просто в формате 79115576362 (без @c.us)
            $requestBody = [
                'recipient' => $phone, // Поле называется "recipient", не "to"
                'body' => $message,
            ];
            
            Log::debug('WhatsApp test message request body', [
                'body' => $requestBody,
                'url' => $fullUrl,
            ]);
            
            $response = \Illuminate\Support\Facades\Http::timeout(60)
                ->connectTimeout(10)
                ->withoutVerifying()
                ->withHeaders([
                    'Authorization' => $authToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->asJson()
                ->post($fullUrl, $requestBody);
            
            if (!$response) {
                throw new \Exception('Не удалось отправить запрос к WhatsApp API');
            }
            
            $responseBody = $response->body();
            $responseData = $response->json();
            
            Log::info('WhatsApp test message response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'response_body' => $responseBody,
                'response_data' => $responseData,
            ]);
            
            if (!$response->successful()) {
                $errorMsg = 'Ошибка отправки сообщения. ';
                
                if ($response->status() === 401 || $response->status() === 403) {
                    $errorMsg .= 'Ошибка авторизации. Проверьте правильность API токена.';
                } elseif ($response->status() === 400) {
                    // Более детальная обработка ошибки 400
                    $errorData = $responseData ?? [];
                    
                    // Пробуем извлечь детальное сообщение об ошибке
                    $errorMessage = $errorData['message'] ?? $errorData['error'] ?? $errorData['detail'] ?? null;
                    
                    if ($errorMessage) {
                        $errorMsg .= $errorMessage;
                    } else {
                        // Если нет детального сообщения, пробуем понять из body
                        if (is_string($responseBody) && !empty($responseBody)) {
                            // Пробуем найти JSON в ответе
                            $jsonStart = strpos($responseBody, '{');
                            if ($jsonStart !== false) {
                                $jsonPart = substr($responseBody, $jsonStart);
                                $parsed = json_decode($jsonPart, true);
                                if ($parsed && isset($parsed['message'])) {
                                    $errorMsg .= $parsed['message'];
                                } else {
                                    $errorMsg .= 'Неверный запрос. Проверьте правильность номера телефона и текста сообщения. Ответ сервера: ' . substr($responseBody, 0, 300);
                                }
                            } else {
                                $errorMsg .= 'Неверный запрос. Проверьте правильность номера телефона и текста сообщения. Ответ сервера: ' . substr($responseBody, 0, 300);
                            }
                        } else {
                            $errorMsg .= 'Неверный запрос. Проверьте правильность номера телефона (формат: 79959640099@c.us) и текста сообщения.';
                        }
                    }
                } else {
                    $errorMsg .= 'HTTP ' . $response->status() . ': ' . substr($responseBody, 0, 300);
                }
                
                Log::warning('WhatsApp test message failed', [
                    'status' => $response->status(),
                    'error_msg' => $errorMsg,
                    'request_url' => $fullUrl,
                    'request_body' => ['to' => $to, 'body' => $message],
                    'response_body' => $responseBody,
                ]);
                
                throw new \Exception($errorMsg);
            }

            return response()->json([
                'success' => true,
                'message' => 'Тестовое сообщение успешно отправлено на номер ' . $phone . '!',
                'data' => $responseData,
            ]);

        } catch (\Exception $e) {
            Log::error('WhatsApp test send failed', [
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
            
            // Проверяем, что есть URL и ключ (только из БД, без fallback)
            if (empty($testUrl) || empty($testKey)) {
                throw new \Exception('URL API и ключ доступа обязательны для проверки подключения. Пожалуйста, настройте их в разделе "API Перевозчика" выше.');
            }
            
            Log::info('Testing Carrier API connection', [
                'test_url' => $testUrl,
                'key_present' => !empty($testKey),
                'key_length' => strlen($testKey),
            ]);
            
            // Выполняем тестовый запрос напрямую с правильными настройками
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->connectTimeout(10)
                ->withHeaders([
                    'x-access-token' => $testKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->get(rtrim($testUrl, '/') . '/stations');
            
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
            
            $responseData = $response->json();
            
            // API может вернуть массив напрямую или объект с полем data
            $stationsData = is_array($responseData) 
                ? ($responseData['data'] ?? $responseData) 
                : [];
            
            $stationsCount = is_array($stationsData) ? count($stationsData) : 0;
            
            Log::info('Carrier API test successful', [
                'stations_count' => $stationsCount,
                'response_type' => gettype($responseData),
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
        if (str_contains($key, '_limit') || str_contains($key, '_count') || str_contains($key, 'timeout') || str_contains($key, 'interval') || str_contains($key, '_seconds')) {
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

