<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StartportPassengerService
{
    /**
     * Получить пассажиров для рейса из API Startport
     *
     * @param string|int $routeId ID рейса (external_id)
     * @return array
     * @throws \Exception
     */
    public function getPassengersByRoute($routeId): array
    {
        $url = $this->getApiUrl();
        $apiKey = $this->getApiKey();

        if (empty($url) || empty($apiKey)) {
            throw new \Exception('Startport API не настроен. Укажите URL и API ключ в настройках.');
        }

        // Формируем полный URL для запроса
        $endpoint = str_replace('{{route_id}}', $routeId, '/api/passengers/race/{{route_id}}');
        $fullUrl = rtrim($url, '/') . $endpoint;

        Log::info('Загрузка пассажиров из Startport API', [
            'route_id' => $routeId,
            'url' => $fullUrl,
        ]);

        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->get($fullUrl);

            if (!$response->successful()) {
                Log::error('Ошибка загрузки пассажиров из Startport', [
                    'route_id' => $routeId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \Exception(
                    'Ошибка загрузки пассажиров из Startport: HTTP ' . $response->status()
                );
            }

            $data = $response->json();

            // Проверяем структуру ответа
            if (!is_array($data)) {
                Log::warning('Неожиданная структура ответа от Startport', [
                    'route_id' => $routeId,
                    'response_type' => gettype($data),
                ]);
                return [];
            }

            // Если данные в поле data
            $passengers = $data['data'] ?? $data;

            if (!is_array($passengers)) {
                return [];
            }

            Log::info('Пассажиры загружены из Startport', [
                'route_id' => $routeId,
                'count' => count($passengers),
            ]);

            return $passengers;

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Ошибка подключения к Startport API', [
                'route_id' => $routeId,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception('Не удалось подключиться к Startport API: ' . $e->getMessage());
        }
    }

    /**
     * Нормализовать данные пассажира из Startport в формат системы
     *
     * @param array $passengerData
     * @param int $tripId
     * @param string $externalRaceId
     * @return array
     */
    public function normalizePassengerData(array $passengerData, int $tripId, string $externalRaceId): array
    {
        // Обрабатываем отчество "нет_отчества"
        $middleName = $passengerData['middleName'] ?? null;
        if ($middleName === 'нет_отчества' || $middleName === 'НЕТ_ОТЧЕСТВА') {
            $middleName = null;
        }

        // Извлекаем данные пользователя для email и phone
        $userData = $passengerData['user'] ?? [];
        $email = $userData['email'] ?? null;
        $phone = $userData['phone'] ?? null;

        // Генерируем уникальный идентификатор билета из documentId и первого ticketId
        $ticketUid = $passengerData['documentId'] ?? null;
        if (!empty($passengerData['ticketIds']) && is_array($passengerData['ticketIds'])) {
            $ticketUid = $passengerData['documentId'] . '_' . $passengerData['ticketIds'][0];
        }

        return [
            'trip_id' => $tripId,
            'external_race_id' => $externalRaceId,
            'external_booking_id' => $this->stringValue($passengerData['documentId'] ?? $passengerData['id'] ?? null),
            'external_order_id' => $this->integerValue($passengerData['id'] ?? null),
            'first_name' => $this->normalizeName($passengerData['firstName'] ?? null),
            'last_name' => $this->normalizeName($passengerData['lastName'] ?? null),
            'middle_name' => $this->normalizeName($middleName),
            'email' => $this->normalizeEmail($email),
            'phone' => $this->sanitizePhone($phone),
            'birth_date' => $this->parseDate($passengerData['birthDate'] ?? null),
            'document_type' => $this->stringValue($passengerData['documentType'] ?? null),
            'document_series' => $this->stringValue($passengerData['documentSeries'] ?? null),
            'document_number' => $this->stringValue($passengerData['documentNumber'] ?? null),
            'document_issued_at' => null, // В API startport нет этого поля
            'seat_number' => null, // Пока не известно где находится номер места
            'ticket_number' => $this->stringValue($ticketUid),
            'ticket_uid' => $this->stringValue($ticketUid),
            'ticket_price' => null, // Информации о цене нет в предоставленных данных
            'ticket_service_fee' => null,
            'ticket_total_price' => null,
            'ticket_discount' => null,
            'ticket_status' => 'paid', // Предполагаем что билет оплачен, если он в списке
            'passenger_type' => $this->mapAgeCategory($passengerData['ageCategory'] ?? null),
            'ticket_purchased_at' => $this->parseDateTime($passengerData['createdAt'] ?? null),
            'external_payload' => $passengerData,
            'data_source' => 'startport',
        ];
    }

    /**
     * Преобразование категории возраста в тип пассажира
     */
    protected function mapAgeCategory(?string $ageCategory): ?string
    {
        return match (strtolower((string) $ageCategory)) {
            'adult' => 'ВЗРОСЛЫЙ',
            'child' => 'ДЕТСКИЙ',
            'infant' => 'ДЕТСКИЙ',
            default => null,
        };
    }

    /**
     * Получить URL API из настроек
     */
    protected function getApiUrl(): ?string
    {
        $url = Setting::get('startport_url');
        
        // Автоматически преобразуем HTTP в HTTPS для startport.ru
        if ($url && str_starts_with($url, 'http://startport.ru')) {
            $url = str_replace('http://', 'https://', $url);
        }
        
        return $url;
    }

    /**
     * Получить API ключ из настроек
     */
    protected function getApiKey(): ?string
    {
        return Setting::get('startport_api_key');
    }

    /**
     * Проверить, включен ли импорт из Startport
     */
    public function isEnabled(): bool
    {
        $url = $this->getApiUrl();
        $apiKey = $this->getApiKey();

        return !empty($url) && !empty($apiKey);
    }

    /**
     * Нормализация телефона
     */
    protected function sanitizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/[^\d+]/', '', $phone);

        if (!$digits) {
            return null;
        }

        if (str_starts_with($digits, '8') && strlen($digits) === 11) {
            $digits = '7' . substr($digits, 1);
        }

        if (!str_starts_with($digits, '7') && !str_starts_with($digits, '+7')) {
            $digits = '+' . $digits;
        }

        if (str_starts_with($digits, '7')) {
            $digits = '+' . $digits;
        }

        return $digits;
    }

    /**
     * Нормализация имени
     */
    protected function normalizeName(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = mb_strtolower($value, 'UTF-8');

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Нормализация email
     */
    protected function normalizeEmail(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    /**
     * Преобразование в строку
     */
    protected function stringValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Преобразование в число
     */
    protected function integerValue($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Преобразование в decimal
     */
    protected function decimalValue($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace([' ', "\u{00A0}"], '', $value);
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * Преобразование даты
     */
    protected function parseDate($value): ?\Carbon\Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Преобразование даты/времени
     */
    protected function parseDateTime($value): ?\Carbon\Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Преобразование статуса билета
     */
    protected function mapTicketStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'COMPLETED', 'PAID', 'CONFIRMED' => 'paid',
            'CANCELLED' => 'cancelled',
            'REFUND', 'REFUNDED' => 'refunded',
            default => 'booked',
        };
    }
}
