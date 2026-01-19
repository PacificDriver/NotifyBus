<?php

namespace App\Services;

use App\Models\ImportState;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\Setting;
use App\Models\Station;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PbOrderItemImporter
{
    /**
     * Импортировать данные из таблицы pb_order_item в локальные сущности.
     *
     * @param  array{
     *     since_id?: int,
     *     chunk?: int,
     *     dry_run?: bool,
     *     race_ids?: array<int,string|int>
     * }  $options
     * @return array<string,int>
     */
    public function import(array $options = []): array
    {
        $sinceId = $options['since_id'] ?? $this->getLastProcessedId();
        $chunkSize = $options['chunk'] ?? 500;
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $filterRaceIds = $options['race_ids'] ?? null;

        $stats = [
            'rows_read' => 0,
            'trips_created' => 0,
            'trips_updated' => 0,
            'passengers_upserted' => 0,
            'stations_created' => 0,
            'routes_created' => 0,
            'last_processed_id' => $sinceId ?? 0,
        ];

        $sourceTable = Setting::get('importer_source_table', 'pb_order_item');

        if (!Schema::hasTable($sourceTable)) {
            Log::error("Importer source table not found", ['table' => $sourceTable]);
            throw new \RuntimeException("Таблица '{$sourceTable}' недоступна. Проверьте настройку «Импорт → Таблица источника» в админке.");
        }

        // Логируем параметры импорта для отладки
        Log::info('PbOrderItemImporter: Starting import', [
            'source_table' => $sourceTable,
            'since_id' => $sinceId,
            'filter_race_ids' => $filterRaceIds,
            'chunk_size' => $chunkSize,
            'dry_run' => $dryRun,
        ]);

        $query = DB::table($sourceTable)->orderBy('ID');

        // Если указаны конкретные race_ids, игнорируем sinceId для этих рейсов
        // Это позволяет импортировать конкретные рейсы независимо от sinceId
        if ($filterRaceIds) {
            $query->whereIn('RACE_ID', array_map('trim', $filterRaceIds));
            // При фильтре по race_ids игнорируем sinceId, чтобы импортировать все записи для этих рейсов
        } elseif ($sinceId !== null && $sinceId > 0) {
            // Используем sinceId только если он больше 0 и не указаны race_ids
            $query->where('ID', '>', $sinceId);
        }
        // Если sinceId === 0 или null и нет filterRaceIds, импортируем все записи

        $query->chunkById($chunkSize, function ($rows) use ($dryRun, &$stats, $filterRaceIds) {
            $chunkStartId = null;
            $chunkEndId = null;
            $passengersInChunk = 0;
            
            foreach ($rows as $row) {
                if ($chunkStartId === null) {
                    $chunkStartId = $row->ID;
                }
                $chunkEndId = $row->ID;
                
                $stats['rows_read']++;
                $stats['last_processed_id'] = $row->ID;

                $rowData = (array) $row;

                $result = $this->importRow($rowData, $dryRun);

                $stats['stations_created'] += $result['stations_created'];
                $stats['routes_created'] += $result['routes_created'];
                $stats['trips_created'] += $result['trip_created'] ? 1 : 0;
                $stats['trips_updated'] += $result['trip_updated'] ? 1 : 0;
                if ($result['passenger_upserted']) {
                    $stats['passengers_upserted']++;
                    $passengersInChunk++;
                }
            }
            
            // Логируем информацию о чанке для отладки
            if ($filterRaceIds) {
                Log::info('PbOrderItemImporter: Processed chunk', [
                    'chunk_size' => count($rows),
                    'start_id' => $chunkStartId,
                    'end_id' => $chunkEndId,
                    'filter_race_ids' => $filterRaceIds,
                    'passengers_upserted_in_chunk' => $passengersInChunk,
                ]);
            }
        }, 'ID');

        if (!$dryRun && $stats['rows_read'] > 0) {
            $this->rememberLastProcessedId($stats['last_processed_id']);
        }

        return $stats;
    }

    /**
     * Получить последний обработанный ID.
     */
    protected function getLastProcessedId(): ?int
    {
        $state = ImportState::firstWhere('key', 'pb_order_item');

        return $state?->value['last_id'] ?? null;
    }

    /**
     * Запомнить последний обработанный ID.
     */
    protected function rememberLastProcessedId(int $lastId): void
    {
        ImportState::updateOrCreate(
            ['key' => 'pb_order_item'],
            ['value' => ['last_id' => $lastId]]
        );
    }

    /**
     * Обработка одной строки pb_order_item.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,bool|int>
     */
    protected function importRow(array $data, bool $dryRun = false): array
    {
        $result = [
            'stations_created' => 0,
            'routes_created' => 0,
            'trip_created' => false,
            'trip_updated' => false,
            'passenger_upserted' => false,
        ];

        $raceId = $this->stringValue($data['RACE_ID'] ?? null);

        if (!$raceId) {
            Log::debug('PbOrderItemImporter: Skipping row without RACE_ID', [
                'row_id' => $data['ID'] ?? null,
            ]);
            return $result;
        }

        [$fromStation, $newFrom] = $this->resolveStation(
            $data['FROM_ID'] ?? null,
            $data['FROM_LABEL'] ?? null
        );

        [$toStation, $newTo] = $this->resolveStation(
            $data['TO_ID'] ?? null,
            $data['TO_LABEL'] ?? null
        );

        $result['stations_created'] += $newFrom + $newTo;

        [$route, $routeCreated] = $this->resolveRoute($fromStation, $toStation, $data);
        if ($routeCreated) {
            $result['routes_created']++;
        }

        [$trip, $tripCreated, $tripUpdated] = $this->resolveTrip($route, $data);
        $result['trip_created'] = $tripCreated;
        $result['trip_updated'] = $tripUpdated;

        if ($dryRun) {
            return $result;
        }

        try {
            $passenger = $this->upsertPassenger($trip, $data);

            if ($passenger) {
                $result['passenger_upserted'] = true;
                Log::debug('PbOrderItemImporter: Passenger upserted', [
                    'passenger_id' => $passenger->id,
                    'trip_id' => $trip->id,
                    'trip_external_id' => $trip->external_id,
                    'race_id' => $raceId,
                    'ticket_uid' => $passenger->ticket_uid,
                ]);
            } else {
                Log::warning('PbOrderItemImporter: Failed to upsert passenger', [
                    'trip_id' => $trip->id,
                    'trip_external_id' => $trip->external_id,
                    'race_id' => $raceId,
                    'row_id' => $data['ID'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('PbOrderItemImporter: Error upserting passenger', [
                'trip_id' => $trip->id,
                'trip_external_id' => $trip->external_id,
                'race_id' => $raceId,
                'row_id' => $data['ID'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $result;
    }

    /**
     * Нормализация станции.
     *
     * @param  mixed  $externalId
     * @param  mixed  $label
     * @return array{0: Station, 1: int}
     */
    protected function resolveStation($externalId, $label): array
    {
        $externalId = $this->stringValue($externalId);
        $label = trim((string) $label);

        if (!$externalId) {
            $station = Station::firstOrCreate(
                ['name' => $label ?: 'Неизвестная станция'],
                [
                    'external_id' => null,
                    'code' => null,
                    'city' => null,
                    'region' => 'Сахалинская область',
                    'is_active' => true,
                ]
            );

            return [$station, $station->wasRecentlyCreated ? 1 : 0];
        }

        $station = Station::where('external_id', $externalId)->first();

        if ($station) {
            $updated = false;

            if ($label && $station->name !== $label) {
                $station->name = $label;
                $updated = true;
            }

            if (!$station->is_active) {
                $station->is_active = true;
                $updated = true;
            }

            if ($updated) {
                $station->save();
            }

            return [$station, 0];
        }

        $station = Station::create([
            'external_id' => $externalId,
            'name' => $label ?: "Станция {$externalId}",
            'code' => null,
            'city' => null,
            'region' => 'Сахалинская область',
            'is_active' => true,
        ]);

        return [$station, 1];
    }

    /**
     * Определение маршрута.
     *
     * @return array{0: Route, 1: bool}
     */
    protected function resolveRoute(Station $fromStation, Station $toStation, array $data): array
    {
        $routeNumber = $this->stringValue($data['RACE_NUMBER'] ?? null) ?: $this->stringValue($data['RACE_ID'] ?? null);

        $route = Route::where('departure_station_id', $fromStation->id)
            ->where('arrival_station_id', $toStation->id)
            ->first();

        if (!$route) {
            $route = Route::create([
                'departure_station_id' => $fromStation->id,
                'arrival_station_id' => $toStation->id,
                'route_number' => $routeNumber,
                'is_active' => true,
            ]);

            return [$route, true];
        }

        $updated = false;

        if ($routeNumber && $route->route_number !== $routeNumber) {
            $route->route_number = $routeNumber;
            $updated = true;
        }

        if (!$route->is_active) {
            $route->is_active = true;
            $updated = true;
        }

        if ($updated) {
            $route->save();
        }

        return [$route, false];
    }

    /**
     * Определение рейса.
     *
     * @return array{0: Trip, 1: bool, 2: bool}
     */
    protected function resolveTrip(Route $route, array $data): array
    {
        $raceId = $this->stringValue($data['RACE_ID'] ?? null);
        $departureTime = $this->parseDateTime($data['ROUTE_BEGIN'] ?? null);
        $arrivalTime = $this->parseDateTime($data['ROUTE_END'] ?? null);
        $status = $this->mapTripStatus($data['STATUS'] ?? null);
        $tripNumber = $this->stringValue($data['RACE_NUMBER'] ?? null) ?: $raceId;

        $trip = Trip::where('external_id', $raceId)->first();

        if (!$trip) {
            $trip = Trip::create([
                'route_id' => $route->id,
                'trip_number' => $tripNumber,
                'external_id' => $raceId,
                'departure_time' => $departureTime,
                'arrival_time' => $arrivalTime ?? $departureTime?->copy()->addHour(),
                'status' => $status,
                'cancellation_reason' => null,
                'cancelled_at' => $status === 'cancelled' ? now() : null,
                'delay_minutes' => null,
                'total_seats' => null,
                'available_seats' => null,
            ]);

            return [$trip, true, false];
        }

        $updated = false;

        if ($trip->route_id !== $route->id) {
            $trip->route_id = $route->id;
            $updated = true;
        }

        if ($departureTime && !$trip->departure_time?->equalTo($departureTime)) {
            $trip->departure_time = $departureTime;
            $updated = true;
        }

        if ($arrivalTime && !$trip->arrival_time?->equalTo($arrivalTime)) {
            $trip->arrival_time = $arrivalTime;
            $updated = true;
        }

        $mappedStatus = $this->mapTripStatus($data['STATUS'] ?? null);
        if ($trip->status !== $mappedStatus) {
            $trip->status = $mappedStatus;
            if ($mappedStatus === 'cancelled') {
                $trip->cancelled_at = $trip->cancelled_at ?? now();
            }
            $updated = true;
        }

        if ($updated) {
            $trip->save();
        }

        return [$trip, false, $updated];
    }

    /**
     * Создание/обновление пассажира.
     *
     * @param  array<string,mixed>  $data
     */
    protected function upsertPassenger(Trip $trip, array $data): ?Passenger
    {
        $raceId = $this->stringValue($data['RACE_ID'] ?? null);

        if (!$raceId) {
            return null;
        }

        $ticketUid = $this->stringValue($data['TICKET_REFUND_ID'] ?? $data['ID'] ?? null);
        $bookingId = $this->stringValue($data['TICKET_REFUND_ID'] ?? $data['ORDER_ID'] ?? $data['ID'] ?? null);

        $attributes = [
            'external_race_id' => $raceId,
            'ticket_uid' => $ticketUid ?: null,
        ];

        $values = [
            'trip_id' => $trip->id,
            'external_booking_id' => $bookingId,
            'external_order_id' => $this->integerValue($data['ORDER_ID'] ?? null),
            'passenger_type' => $this->mapPassengerType($data['TYPE'] ?? null),
            'first_name' => $this->normalizeName($data['CLIENT_NAME'] ?? null),
            'last_name' => $this->normalizeName($data['CLIENT_SURNAME'] ?? null),
            'middle_name' => $this->normalizeName($data['CLIENT_PATRONYMIC'] ?? null),
            'email' => $this->normalizeEmail($data['CLIENT_EMAIL'] ?? null),
            'phone' => $this->sanitizePhone($data['CLIENT_PHONE'] ?? null),
            'birth_date' => $this->parseDate($data['CLIENT_BIRTH'] ?? null),
            'document_type' => $this->stringValue($data['CLIENT_DOC_ID'] ?? null),
            'document_series' => $this->stringValue($data['CLIENT_DOC_SERIES'] ?? null),
            'document_number' => $this->stringValue($data['CLIENT_DOC_NUMBER'] ?? null),
            'document_issued_at' => $this->parseDate($data['CLIENT_DOC_DATE'] ?? null),
            'seat_number' => $this->stringValue($data['SEAT'] ?? null),
            'ticket_number' => $ticketUid ?: $bookingId,
            'ticket_price' => $this->decimalValue($data['COST'] ?? null),
            'ticket_service_fee' => $this->decimalValue($data['COST_TAX'] ?? null),
            'ticket_total_price' => $this->decimalValue($data['TOTAL_COST'] ?? null),
            'ticket_discount' => $this->decimalValue($data['BAG_COST'] ?? null),
            'ticket_status' => $this->mapTicketStatus($data['STATUS'] ?? null),
            'ticket_purchased_at' => $this->parseDateTime($data['ROUTE_BEGIN'] ?? null),
            'external_payload' => $data,
            'data_source' => 'pb_order_item',
        ];

        // Фильтруем null значения, но всегда включаем trip_id, даже если он null
        $filteredValues = array_filter($values, static fn ($value) => $value !== null);
        
        // Гарантируем, что trip_id всегда обновляется, даже если пассажир уже существует
        $filteredValues['trip_id'] = $trip->id;

        $passenger = Passenger::updateOrCreate($attributes, $filteredValues);
        
        // Дополнительная проверка: если trip_id не совпадает, обновляем вручную
        // Это нужно на случай, если updateOrCreate не обновил trip_id из-за уникального индекса
        if ($passenger->trip_id !== $trip->id) {
            $passenger->trip_id = $trip->id;
            $passenger->save();
        }

        return $passenger;
    }

    /**
     * Преобразование статуса рейса.
     */
    protected function mapTripStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'CANCELLED', 'REFUND', 'REFUNDED' => 'cancelled',
            default => 'scheduled',
        };
    }

    /**
     * Преобразование статуса билета.
     */
    protected function mapTicketStatus(?string $status): string
    {
        return match (strtoupper((string) $status)) {
            'COMPLETED', 'PAID' => 'paid',
            'CANCELLED' => 'cancelled',
            'REFUND', 'REFUNDED' => 'refunded',
            default => 'booked',
        };
    }

    /**
     * Преобразование типа пассажира.
     */
    protected function mapPassengerType(?string $type): ?string
    {
        $type = strtoupper(trim((string) $type));

        return $type ?: null;
    }

    /**
     * Нормализация телефона.
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

        if (Str::startsWith($digits, '8') && strlen($digits) === 11) {
            $digits = '7'.substr($digits, 1);
        }

        if (Str::startsWith($digits, '7') === false && Str::startsWith($digits, '+7') === false) {
            $digits = '+'.$digits;
        }

        if (Str::startsWith($digits, '7')) {
            $digits = '+'.$digits;
        }

        return $digits;
    }

    /**
     * Нормализация имени.
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
     * Нормализация email.
     */
    protected function normalizeEmail(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    /**
     * Преобразование в строку.
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
     * Преобразование в число.
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
     * Преобразование в decimal.
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
     * Преобразование даты.
     */
    protected function parseDate($value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Преобразование даты/времени.
     */
    protected function parseDateTime($value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Загрузить пассажиров из Startport API для отмененных рейсов
     * 
     * @return array{startport_trips_processed: int, startport_passengers_loaded: int, startport_passengers_saved: int, startport_errors: int}
     */
    public function loadStartportPassengers(): array
    {
        $stats = [
            'startport_trips_processed' => 0,
            'startport_passengers_loaded' => 0,
            'startport_passengers_saved' => 0,
            'startport_errors' => 0,
        ];

        $startportService = app(\App\Services\StartportPassengerService::class);

        // Проверяем, включен ли Startport
        if (!$startportService->isEnabled()) {
            Log::info('PbOrderItemImporter: Startport API не настроен, пропускаем загрузку пассажиров');
            return $stats;
        }

        // Получаем все рейсы (без фильтрации по статусу и дате)
        // Это аналогично старому импорту, который загружает все записи из pb_order_item
        $trips = Trip::all();

        if ($trips->isEmpty()) {
            Log::info('PbOrderItemImporter: Нет рейсов для загрузки из Startport');
            return $stats;
        }

        Log::info('PbOrderItemImporter: Начинаем загрузку пассажиров из Startport', [
            'trips_count' => $trips->count(),
        ]);

        foreach ($trips as $trip) {
            $stats['startport_trips_processed']++;

            try {
                $startportPassengers = $startportService->getPassengersByRoute($trip->external_id);
                $stats['startport_passengers_loaded'] += count($startportPassengers);

                Log::debug('PbOrderItemImporter: Загружены пассажиры из Startport', [
                    'trip_id' => $trip->id,
                    'external_id' => $trip->external_id,
                    'passengers_count' => count($startportPassengers),
                ]);

                // Сохраняем пассажиров в базу данных
                foreach ($startportPassengers as $passengerData) {
                    try {
                        $normalizedData = $startportService->normalizePassengerData(
                            $passengerData,
                            $trip->id,
                            $trip->external_id
                        );

                        // Используем documentId + ticketId как уникальный идентификатор
                        $uniqueAttributes = [
                            'external_race_id' => $normalizedData['external_race_id'],
                            'ticket_uid' => $normalizedData['ticket_uid'],
                        ];

                        // Фильтруем null значения для обновления
                        $updateData = array_filter($normalizedData, fn($value) => $value !== null);
                        $updateData['trip_id'] = $trip->id; // Всегда обновляем trip_id

                        Passenger::updateOrCreate($uniqueAttributes, $updateData);
                        $stats['startport_passengers_saved']++;

                    } catch (\Exception $e) {
                        $stats['startport_errors']++;
                        Log::error('PbOrderItemImporter: Ошибка сохранения пассажира из Startport', [
                            'trip_id' => $trip->id,
                            'passenger_data' => $passengerData,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

            } catch (\Exception $e) {
                $stats['startport_errors']++;
                Log::warning('PbOrderItemImporter: Ошибка загрузки пассажиров из Startport для рейса', [
                    'trip_id' => $trip->id,
                    'external_id' => $trip->external_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('PbOrderItemImporter: Загрузка пассажиров из Startport завершена', $stats);

        return $stats;
    }
}


