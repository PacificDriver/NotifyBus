<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Сервис для подключения к внешней базе данных сайта (PostgreSQL)
 * для загрузки пассажиров по ID рейсов
 */
class ExternalDatabaseService
{
    protected ?string $connectionName = null;
    
    public function __construct()
    {
        // Не настраиваем подключение сразу - оно будет настроено при первом использовании
        // Это позволяет контроллерам создаваться даже если настройки внешней БД не заданы
    }

    /**
     * Настроить подключение к внешней БД
     * Вызывается автоматически при первом использовании методов, требующих подключения
     */
    protected function configureConnection(): void
    {
        // Если подключение уже настроено, не настраиваем повторно
        if ($this->connectionName) {
            return;
        }

        // 1. Поддерживаем использование заранее сконфигурированного подключения
        //    (например, если в config/database.php добавлено соединение и его имя
        //    сохранено в настройке external_db_connection_name)
        $predefinedConnection = $this->getSetting('connection_name');
        if ($predefinedConnection && Config::has("database.connections.{$predefinedConnection}")) {
            $this->connectionName = $predefinedConnection;
            Log::info("External database will use predefined connection", [
                'connection' => $predefinedConnection,
            ]);

            return;
        }

        // 2. Пытаемся настроить отдельное подключение по реквизитам из настроек
        $host = $this->getSetting('host');
        $port = $this->getSetting('port', 5432);
        $database = $this->getSetting('database');
        $username = $this->getSetting('username');
        $password = $this->getSetting('password');

        if (!empty($host) && !empty($database) && !empty($username)) {
            try {
                // Создаем временное подключение
                $this->connectionName = 'external_db_' . uniqid();

                Config::set("database.connections.{$this->connectionName}", [
                    'driver' => 'pgsql',
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'username' => $username,
                    'password' => $password,
                    'charset' => 'utf8',
                    'prefix' => '',
                    'prefix_indexes' => true,
                    'search_path' => 'public',
                    'sslmode' => 'prefer',
                ]);

                Log::info("External database connection configured", [
                    'host' => $host,
                    'database' => $database,
                    'username' => $username,
                ]);

                return;
            } catch (\Exception $e) {
                Log::warning("Failed to configure dedicated external database connection", [
                    'error' => $e->getMessage(),
                ]);
                // Продолжаем и попробуем fallback на дефолтное подключение
            }
        }

        // 3. Fallback: используем основное (дефолтное) подключение приложения
        $defaultConnection = Config::get('database.default');

        if ($defaultConnection && Config::has("database.connections.{$defaultConnection}")) {
            $this->connectionName = $defaultConnection;

            Log::info("External database falling back to default connection", [
                'connection' => $defaultConnection,
                'database' => Config::get("database.connections.{$defaultConnection}.database"),
                'host' => Config::get("database.connections.{$defaultConnection}.host"),
            ]);

            return;
        }

        Log::warning("Failed to configure external database connection", [
            'error' => 'External database connection parameters not configured and no default connection available.',
        ]);
        // Не выбрасываем исключение - оно будет выброшено при попытке фактического запроса
    }

    /**
     * Получить настройку из БД
     */
    protected function getSetting(string $key, $default = null)
    {
        try {
            $fullKey = "external_db_{$key}";
            return \App\Models\Setting::get($fullKey) ?? $default;
        } catch (\Exception $e) {
            return $default;
        }
    }

    /**
     * Получить список пассажиров для рейсов по их external_id (ID рейса из API перевозчика)
     * 
     * @param array $raceIds Массив ID рейсов (external_id)
     * @return array Массив пассажиров с данными билетов
     */
    public function getPassengersByRaceIds(array $raceIds): array
    {
        if (empty($raceIds)) {
            return [];
        }

        // Настраиваем подключение при первом использовании
        $this->configureConnection();

        if (!$this->connectionName) {
            throw new \Exception('Настройки внешней базы данных не заданы. Пожалуйста, настройте подключение к внешней БД в админ-панели (раздел "Внешняя БД").');
        }

        try {
            // Получаем имя таблицы билетов из настроек
            $ticketsTable = $this->getSetting('tickets_table', 'carrier_tickets');
            $raceIdColumn = $this->getSetting('race_id_column', 'race_id');
            
            // Запрос к внешней БД
            // Предполагаемая структура таблицы билетов:
            // - id (ID билета)
            // - race_id (ID рейса из API перевозчика)
            // - passenger_first_name / first_name
            // - passenger_last_name / last_name
            // - passenger_middle_name / middle_name
            // - email
            // - phone
            // - seat_number
            // - price
            // - status
            
            $passengers = DB::connection($this->connectionName)
                ->table($ticketsTable)
                ->whereIn($raceIdColumn, $raceIds)
                ->get();

            $result = [];
            foreach ($passengers as $ticket) {
                $payload = $this->convertPayload($ticket);
                $raceIdentifier = $payload[$raceIdColumn] ?? $payload['race_id'] ?? null;

                // Нормализуем данные в единый формат
                $result[] = [
                    'external_booking_id' => $this->stringify(
                        $payload['id']
                        ?? $payload['booking_id']
                        ?? $payload['ticket_id']
                        ?? $payload['external_booking_id']
                        ?? $payload['ticket_uid']
                        ?? null
                    ),
                    'external_order_id' => $payload['order_id']
                        ?? $payload['external_order_id']
                        ?? null,
                    'race_id' => $this->stringify($raceIdentifier),
                    'passenger_type' => $payload['passenger_type']
                        ?? $payload['category']
                        ?? $payload['passenger_category']
                        ?? null,
                    'first_name' => $payload['passenger_first_name']
                        ?? $payload['first_name']
                        ?? $payload['fname']
                        ?? $payload['passenger_name']
                        ?? '',
                    'last_name' => $payload['passenger_last_name']
                        ?? $payload['last_name']
                        ?? $payload['surname']
                        ?? $payload['lname']
                        ?? '',
                    'middle_name' => $payload['passenger_middle_name']
                        ?? $payload['middle_name']
                        ?? $payload['patronymic']
                        ?? $payload['mname']
                        ?? null,
                    'email' => $payload['email']
                        ?? $payload['e_mail']
                        ?? $payload['mail']
                        ?? null,
                    'phone' => $this->sanitizePhone(
                        $payload['phone']
                        ?? $payload['phone_number']
                        ?? $payload['tel']
                        ?? $payload['mobile']
                        ?? null
                    ),
                    'birth_date' => $this->normalizeDate(
                        $payload['birth_date']
                        ?? $payload['birthday']
                        ?? $payload['date_of_birth']
                        ?? null
                    ),
                    'document_type' => $payload['document_type']
                        ?? $payload['doc_type']
                        ?? $payload['document']
                        ?? null,
                    'document_series' => $this->stringify(
                        $payload['document_series']
                        ?? $payload['doc_series']
                        ?? $payload['document_series_code']
                        ?? null
                    ),
                    'document_number' => $this->stringify(
                        $payload['document_number']
                        ?? $payload['doc_number']
                        ?? $payload['document_no']
                        ?? null
                    ),
                    'document_issued_at' => $this->normalizeDate(
                        $payload['document_issued_at']
                        ?? $payload['doc_issued_at']
                        ?? $payload['document_date']
                        ?? null
                    ),
                    'seat_number' => $this->stringify(
                        $payload['seat_number']
                        ?? $payload['seat']
                        ?? $payload['place']
                        ?? null
                    ),
                    'ticket_price' => $this->normalizeDecimal(
                        $payload['price']
                        ?? $payload['ticket_price']
                        ?? $payload['cost']
                        ?? $payload['amount']
                        ?? null
                    ),
                    'ticket_service_fee' => $this->normalizeDecimal(
                        $payload['service_fee']
                        ?? $payload['agency_fee']
                        ?? $payload['commission']
                        ?? null
                    ),
                    'ticket_total_price' => $this->normalizeDecimal(
                        $payload['total_amount']
                        ?? $payload['ticket_total']
                        ?? $payload['price_total']
                        ?? $payload['paid_amount']
                        ?? null
                    ),
                    'ticket_discount' => $this->normalizeDecimal(
                        $payload['discount']
                        ?? $payload['ticket_discount']
                        ?? null
                    ),
                    'ticket_status' => $this->normalizeTicketStatus(
                        $payload['status'] ?? $payload['ticket_status'] ?? 'booked'
                    ),
                    'ticket_uid' => $this->stringify(
                        $payload['ticket_uid']
                        ?? $payload['ticket_hash']
                        ?? $payload['eticket']
                        ?? $payload['e_ticket']
                        ?? null
                    ),
                    'ticket_number' => $this->stringify(
                        $payload['ticket_number']
                        ?? $payload['ticket_no']
                        ?? $payload['ticket']
                        ?? null
                    ),
                    'ticket_purchased_at' => $this->normalizeDateTime(
                        $payload['ticket_purchase_date']
                        ?? $payload['purchased_at']
                        ?? $payload['purchase_date']
                        ?? $payload['created_at']
                        ?? null
                    ),
                    'purchase_source' => $this->normalizePurchaseSource(
                        $payload['purchase_source']
                        ?? $payload['PurchaseSource']
                        ?? $payload['source']
                        ?? null
                    ),
                    'raw_payload' => $payload,
                ];
            }

            Log::info("Loaded passengers from external database", [
                'race_ids_count' => count($raceIds),
                'passengers_count' => count($result),
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error("Failed to load passengers from external database", [
                'race_ids' => $raceIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Нормализовать статус билета
     */
    protected function normalizeTicketStatus(string $status): string
    {
        $status = strtolower(trim($status));
        
        $mapping = [
            'booked' => 'booked',
            'reserved' => 'booked',
            'paid' => 'paid',
            'оплачен' => 'paid',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
            'отменен' => 'cancelled',
            'refunded' => 'refunded',
            'возврат' => 'refunded',
        ];

        return $mapping[$status] ?? 'booked';
    }

    /**
     * Преобразовать запись билета в массив
     */
    protected function convertPayload($ticket): array
    {
        if (is_array($ticket)) {
            return $ticket;
        }

        if (is_object($ticket)) {
            $decoded = json_decode(json_encode($ticket), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return (array) $ticket;
        }

        return [];
    }

    /**
     * Преобразовать значение в строку
     */
    protected function stringify($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    /**
     * Нормализовать десятичное значение в формат 0.00
     */
    protected function normalizeDecimal($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(["\u{00A0}", ' '], '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * Нормализовать дату в формат Y-m-d
     */
    protected function normalizeDate($value): ?string
    {
        $carbon = $this->parseCarbon($value);

        return $carbon ? $carbon->format('Y-m-d') : null;
    }

    /**
     * Нормализовать дату/время в формат Y-m-d H:i:s
     */
    protected function normalizeDateTime($value): ?string
    {
        $carbon = $this->parseCarbon($value);

        return $carbon ? $carbon->format('Y-m-d H:i:s') : null;
    }

    /**
     * Попытаться распарсить дату из различных форматов
     */
    protected function parseCarbon($value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_numeric($value)) {
            try {
                return Carbon::createFromTimestamp((int) $value);
            } catch (\Exception) {
                return null;
            }
        }

        $value = trim((string) $value);

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d',
            'd.m.Y H:i:s',
            'd.m.Y',
            'd-m-Y',
            'd/m/Y',
            'd/m/Y H:i:s',
            'Y-m-d\TH:i:sP',
            'Y-m-d\TH:i:s',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Exception) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Очистить номер телефона от лишних символов
     */
    protected function sanitizePhone(?string $phone): ?string
    {
        $phone = $this->stringify($phone);

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

        return $digits;
    }

    /**
     * Нормализовать источник покупки
     */
    protected function normalizePurchaseSource($value): ?string
    {
        if (!$value) {
            return null;
        }

        $source = strtolower(trim((string) $value));

        // Маппинг значений из БД starport
        $mapping = [
            'vk_app' => 'vk_app',
            'website' => 'website',
            'mobile_app' => 'mobile_app',
            'driver' => 'driver',
            'cashier' => 'cashier',
            'касса' => 'cashier',
            'водитель' => 'driver',
            'сайт' => 'website',
            'вк' => 'vk_app',
            'мобильное приложение' => 'mobile_app',
        ];

        return $mapping[$source] ?? $source;
    }

    /**
     * Проверить подключение к внешней БД
     */
    public function checkConnection(): bool
    {
        try {
            // Настраиваем подключение при проверке
            $this->configureConnection();

            if (!$this->connectionName) {
                return false;
            }

            DB::connection($this->connectionName)->getPdo();
            return true;

        } catch (\Exception $e) {
            Log::warning("External database connection check failed", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

