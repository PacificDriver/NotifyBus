<?php

namespace App\Services;

use App\Models\Passenger;
use App\Models\Route;
use App\Models\Station;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\ImportState;

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

        $query = DB::table('pb_order_item')->orderBy('ID');

        if ($sinceId) {
            $query->where('ID', '>', $sinceId);
        }

        if ($filterRaceIds) {
            $query->whereIn('RACE_ID', array_map('trim', $filterRaceIds));
        }

        $query->chunkById($chunkSize, function ($rows) use ($dryRun, &$stats) {
            foreach ($rows as $row) {
                $stats['rows_read']++;
                $stats['last_processed_id'] = $row->ID;

                $rowData = (array) $row;

                $result = $this->importRow($rowData, $dryRun);

                $stats['stations_created'] += $result['stations_created'];
                $stats['routes_created'] += $result['routes_created'];
                $stats['trips_created'] += $result['trip_created'] ? 1 : 0;
                $stats['trips_updated'] += $result['trip_updated'] ? 1 : 0;
                $stats['passengers_upserted'] += $result['passenger_upserted'] ? 1 : 0;
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

        $passenger = $this->upsertPassenger($trip, $data);

        if ($passenger) {
            $result['passenger_upserted'] = true;
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
        ];

        return Passenger::updateOrCreate($attributes, array_filter($values, static fn ($value) => $value !== null));
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
}


