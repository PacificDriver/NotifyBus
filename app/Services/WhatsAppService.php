<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class WhatsAppService
{
    protected string $apiUrl;
    protected string $apiToken;
    protected string $fromNumber;
    protected int $dailyLimit;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp.api_url');
        $this->apiToken = config('services.whatsapp.api_token');
        $this->fromNumber = config('services.whatsapp.from_number');
        $this->dailyLimit = config('services.whatsapp.daily_limit', 1000);
    }

    /**
     * Отправить WhatsApp сообщение
     */
    public function send(string $to, string $message, array $metadata = []): bool
    {
        // Проверяем дневной лимит
        if (!$this->checkDailyLimit()) {
            throw new \Exception('Daily WhatsApp limit exceeded');
        }

        try {
            // Нормализуем номер телефона
            $phoneNumber = $this->normalizePhoneNumber($to);

            // Отправляем запрос к WhatsApp API
            // Здесь используется обобщенный пример, адаптируйте под свой API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/messages', [
                'from' => $this->fromNumber,
                'to' => $phoneNumber,
                'type' => 'text',
                'text' => [
                    'body' => $message,
                ],
            ]);

            if ($response->successful()) {
                $this->incrementDailyCounter();

                Log::info("WhatsApp message sent successfully", [
                    'to' => $phoneNumber,
                    'message_id' => $response->json('id') ?? null,
                ]);

                return true;
            }

            throw new \Exception('WhatsApp API error: ' . $response->body());

        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp message", [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Отправить WhatsApp сообщение с шаблоном
     */
    public function sendTemplate(string $to, string $templateName, array $parameters = []): bool
    {
        if (!$this->checkDailyLimit()) {
            throw new \Exception('Daily WhatsApp limit exceeded');
        }

        try {
            $phoneNumber = $this->normalizePhoneNumber($to);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/messages', [
                'from' => $this->fromNumber,
                'to' => $phoneNumber,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => ['code' => 'ru'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => $parameters,
                        ],
                    ],
                ],
            ]);

            if ($response->successful()) {
                $this->incrementDailyCounter();

                Log::info("WhatsApp template message sent successfully", [
                    'to' => $phoneNumber,
                    'template' => $templateName,
                ]);

                return true;
            }

            throw new \Exception('WhatsApp API error: ' . $response->body());

        } catch (\Exception $e) {
            Log::error("Failed to send WhatsApp template message", [
                'to' => $to,
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Нормализовать номер телефона для WhatsApp
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
}


