<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Сервис для работы с Wappi.pro WhatsApp API
 * 
 * Документация: https://wappi.pro/api-documentation
 */
class WhatsAppService
{
    protected string $apiUrl;
    protected string $apiToken;
    protected string $profileId;
    protected int $dailyLimit;
    protected bool $useAsync;

    public function __construct()
    {
        // Сначала пытаемся получить из БД (настройки), если нет - из config
        $this->apiUrl = $this->getSetting('api_url', config('services.whatsapp.api_url', 'https://api.wappi.pro'));
        $this->apiToken = $this->getSetting('api_token', config('services.whatsapp.api_token'));
        $this->profileId = $this->getSetting('profile_id', config('services.whatsapp.profile_id'));
        $this->dailyLimit = (int) $this->getSetting('daily_limit', config('services.whatsapp.daily_limit', 1000));
        $this->useAsync = (bool) $this->getSetting('use_async', config('services.whatsapp.use_async', true));
    }

    /**
     * Получить настройку из БД или config
     */
    protected function getSetting(string $key, $default = null)
    {
        try {
            $fullKey = "whatsapp_{$key}";
            $setting = \App\Models\Setting::get($fullKey);
            return $setting !== null ? $setting : $default;
        } catch (\Exception $e) {
            // Если таблица settings еще не создана, используем config
            return $default;
        }
    }

    /**
     * Отправить WhatsApp сообщение (синхронно или асинхронно)
     * 
     * @param string $to Номер телефона получателя
     * @param string $message Текст сообщения
     * @param array $metadata Дополнительные данные (для сохранения task_id, message_id и т.д.)
     * @return array Возвращает массив с результатом: ['success' => bool, 'task_id' => string|null, 'message_id' => string|null]
     */
    public function send(string $to, string $message, array $metadata = []): array
    {
        // Проверяем дневной лимит
        if (!$this->checkDailyLimit()) {
            throw new \Exception('Daily WhatsApp limit exceeded');
        }

        // Проверяем наличие необходимых конфигураций
        if (empty($this->apiToken)) {
            throw new \Exception('WhatsApp API token is not configured');
        }

        if (empty($this->profileId)) {
            throw new \Exception('WhatsApp profile_id is not configured');
        }

        try {
            // Нормализуем номер телефона для Wappi (формат: 79959640099@c.us)
            $phoneNumber = $this->normalizePhoneNumberForWappi($to);

            // Выбираем метод отправки
            if ($this->useAsync) {
                return $this->sendAsync($phoneNumber, $message, $metadata);
            } else {
                return $this->sendSync($phoneNumber, $message, $metadata);
            }

        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp message", [
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Отправить сообщение синхронно (выполняется сразу, возвращает результат)
     */
    protected function sendSync(string $to, string $message, array $metadata = []): array
    {
        $endpoint = '/api/v1/sendMessage';
        
        // profile_id передается как query параметр согласно документации Wappi.pro
        $url = $this->apiUrl . $endpoint . '?profile_id=' . urlencode($this->profileId);
        
        $response = Http::withHeaders([
            'Authorization' => $this->apiToken,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'to' => $to,
            'body' => $message,
        ]);

        if ($response->successful()) {
            $this->incrementDailyCounter();
            
            $responseData = $response->json();
            $messageId = $responseData['id'] ?? $responseData['message_id'] ?? null;

            Log::info("WhatsApp message sent successfully (sync)", [
                'to' => $to,
                'message_id' => $messageId,
                'response' => $responseData,
            ]);

            return [
                'success' => true,
                'message_id' => $messageId,
                'task_id' => null,
                'response' => $responseData,
            ];
        }

        $errorBody = $response->body();
        Log::error("WhatsApp API error (sync)", [
            'to' => $to,
            'status' => $response->status(),
            'response' => $errorBody,
        ]);

        throw new \Exception('WhatsApp API error: ' . $errorBody);
    }

    /**
     * Отправить сообщение асинхронно (ставится в очередь, возвращает task_id)
     */
    protected function sendAsync(string $to, string $message, array $metadata = []): array
    {
        $endpoint = '/api/v1/sendMessageAsync';
        
        // profile_id передается как query параметр согласно документации Wappi.pro
        $url = $this->apiUrl . $endpoint . '?profile_id=' . urlencode($this->profileId);
        
        $response = Http::withHeaders([
            'Authorization' => $this->apiToken,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'to' => $to,
            'body' => $message,
        ]);

        if ($response->successful()) {
            $this->incrementDailyCounter();
            
            $responseData = $response->json();
            $taskId = $responseData['task_id'] ?? $responseData['id'] ?? null;

            Log::info("WhatsApp message queued successfully (async)", [
                'to' => $to,
                'task_id' => $taskId,
                'response' => $responseData,
            ]);

            return [
                'success' => true,
                'task_id' => $taskId,
                'message_id' => null,
                'response' => $responseData,
            ];
        }

        $errorBody = $response->body();
        Log::error("WhatsApp API error (async)", [
            'to' => $to,
            'status' => $response->status(),
            'response' => $errorBody,
        ]);

        throw new \Exception('WhatsApp API error: ' . $errorBody);
    }

    /**
     * Нормализовать номер телефона для Wappi.pro
     * Формат: 79959640099@c.us
     */
    protected function normalizePhoneNumberForWappi(string $phone): string
    {
        // Удаляем все символы кроме цифр
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Добавляем код страны, если его нет (для России +7)
        if (!str_starts_with($phone, '7') && strlen($phone) === 10) {
            $phone = '7' . $phone;
        }

        // Убеждаемся, что номер начинается с 7
        if (!str_starts_with($phone, '7')) {
            throw new \Exception("Invalid phone number format: {$phone}");
        }

        // Формат для Wappi: 79959640099@c.us
        return $phone . '@c.us';
    }

    /**
     * Нормализовать номер телефона (универсальный метод, для обратной совместимости)
     */
    protected function normalizePhoneNumber(string $phone): string
    {
        // Удаляем все символы кроме цифр
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Добавляем код страны, если его нет (для России +7)
        if (!str_starts_with($phone, '7') && strlen($phone) === 10) {
            $phone = '7' . $phone;
        }

        return $phone;
    }

    /**
     * Проверить валидность номера телефона
     */
    public function isValidPhoneNumber(string $phone): bool
    {
        $normalized = $this->normalizePhoneNumber($phone);
        return strlen($normalized) >= 10 && strlen($normalized) <= 15;
    }

    /**
     * Проверить дневной лимит отправки
     */
    protected function checkDailyLimit(): bool
    {
        $cacheKey = 'whatsapp_daily_count_' . date('Y-m-d');
        $currentCount = Cache::get($cacheKey, 0);

        return $currentCount < $this->dailyLimit;
    }

    /**
     * Увеличить счетчик отправленных сообщений
     */
    protected function incrementDailyCounter(): void
    {
        $cacheKey = 'whatsapp_daily_count_' . date('Y-m-d');
        $expiresAt = now()->endOfDay();

        Cache::put($cacheKey, Cache::get($cacheKey, 0) + 1, $expiresAt);
    }

    /**
     * Получить текущий счетчик отправленных сообщений за сегодня
     */
    public function getTodayCount(): int
    {
        $cacheKey = 'whatsapp_daily_count_' . date('Y-m-d');
        return Cache::get($cacheKey, 0);
    }

    /**
     * Получить оставшийся лимит на сегодня
     */
    public function getRemainingLimit(): int
    {
        return max(0, $this->dailyLimit - $this->getTodayCount());
    }

    /**
     * Проверить статус профиля WhatsApp
     */
    public function checkProfileStatus(): array
    {
        if (empty($this->apiToken) || empty($this->profileId)) {
            throw new \Exception('WhatsApp API token or profile_id is not configured');
        }

        try {
            $endpoint = '/api/v1/getProfileStatus';
            
            // profile_id передается как query параметр
            $url = $this->apiUrl . $endpoint . '?profile_id=' . urlencode($this->profileId);
            
            $response = Http::withHeaders([
                'Authorization' => $this->apiToken,
                'Content-Type' => 'application/json',
            ])->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            throw new \Exception('Failed to check profile status: ' . $response->body());

        } catch (\Exception $e) {
            Log::error("Failed to check WhatsApp profile status", [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}


