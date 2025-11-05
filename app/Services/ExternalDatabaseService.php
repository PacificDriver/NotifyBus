<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * Сервис для подключения к внешней базе данных сайта (PostgreSQL)
 * для загрузки пассажиров по ID рейсов
 */
class ExternalDatabaseService
{
    protected ?string $connectionName = null;
    
    public function __construct()
    {
        // Получаем настройки подключения к внешней БД из настроек
        $this->configureConnection();
    }

    /**
     * Настроить подключение к внешней БД
     */
    protected function configureConnection(): void
    {
        try {
            // Сначала берем из .env, затем из БД (настройки), затем из config
            $host = env('EXTERNAL_DB_HOST') 
                ?? $this->getSetting('host', config('database.external.host'));
            $port = env('EXTERNAL_DB_PORT') 
                ?? $this->getSetting('port', config('database.external.port', 5432));
            $database = env('EXTERNAL_DB_DATABASE') 
                ?? $this->getSetting('database', config('database.external.database'));
            $username = env('EXTERNAL_DB_USERNAME') 
                ?? $this->getSetting('username', config('database.external.username'));
            $password = env('EXTERNAL_DB_PASSWORD') 
                ?? $this->getSetting('password', config('database.external.password'));

            if (empty($host) || empty($database) || empty($username)) {
                throw new \Exception('External database connection parameters not configured');
            }

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

        } catch (\Exception $e) {
            Log::error("Failed to configure external database connection", [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
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

        if (!$this->connectionName) {
            throw new \Exception('External database connection not configured');
        }

        try {
            // Получаем имя таблицы билетов из настроек
            $ticketsTable = $this->getSetting('tickets_table', 'tickets');
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
                // Нормализуем данные в единый формат
                $result[] = [
                    'external_booking_id' => $ticket->id ?? $ticket->booking_id ?? $ticket->ticket_id ?? null,
                    'race_id' => $ticket->$raceIdColumn ?? $ticket->race_id ?? null,
                    'first_name' => $ticket->passenger_first_name 
                        ?? $ticket->first_name 
                        ?? $ticket->fname 
                        ?? $ticket->passenger_name
                        ?? '',
                    'last_name' => $ticket->passenger_last_name 
                        ?? $ticket->last_name 
                        ?? $ticket->surname 
                        ?? $ticket->lname
                        ?? '',
                    'middle_name' => $ticket->passenger_middle_name 
                        ?? $ticket->middle_name 
                        ?? $ticket->patronymic 
                        ?? $ticket->mname
                        ?? null,
                    'email' => $ticket->email 
                        ?? $ticket->e_mail 
                        ?? $ticket->mail
                        ?? null,
                    'phone' => $ticket->phone 
                        ?? $ticket->phone_number 
                        ?? $ticket->tel 
                        ?? $ticket->mobile
                        ?? null,
                    'seat_number' => $ticket->seat_number 
                        ?? $ticket->seat 
                        ?? $ticket->place
                        ?? null,
                    'ticket_price' => $ticket->price 
                        ?? $ticket->ticket_price 
                        ?? $ticket->cost 
                        ?? $ticket->amount
                        ?? null,
                    'ticket_status' => $this->normalizeTicketStatus(
                        $ticket->status ?? $ticket->ticket_status ?? 'booked'
                    ),
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
     * Проверить подключение к внешней БД
     */
    public function checkConnection(): bool
    {
        try {
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

