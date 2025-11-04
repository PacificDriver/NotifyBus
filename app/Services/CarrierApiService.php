<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Station;
use App\Models\Route;
use App\Models\Trip;
use App\Models\Passenger;

/**
 * Сервис для интеграции с API перевозчика (Сахалин)
 */
class CarrierApiService
{
    protected string $apiUrl;
    protected string $apiKey;
    protected int $timeout;

    public function __construct()
    {
        $this->apiUrl = config('services.carrier_api.url');
        $this->apiKey = config('services.carrier_api.key');
        $this->timeout = config('services.carrier_api.timeout', 30);
    }

    /**
     * Получить базовые заголовки для запросов
     * API использует x-access-token вместо Authorization: Bearer
     */
    protected function getHeaders(): array
    {
        return [
            'x-access-token' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Конвертировать дату в формат API (d.m.y)
     * Пример: 2024-11-07 -> 7.11.24
     */
    protected function formatDateForApi(string $date): string
    {
        try {
            $carbon = Carbon::parse($date);
            return $carbon->format('j.n.y'); // d.m.y без ведущих нулей для дня
        } catch (\Exception $e) {
            Log::warning("Failed to format date for API", [
                'date' => $date,
                'error' => $e->getMessage(),
            ]);
            // Если не удалось распарсить, возвращаем как есть
            return $date;
        }
    }

    /**
     * Выполнить HTTP запрос с обработкой ошибок
     */
    protected function makeRequest(string $method, string $endpoint, array $params = []): array
    {
        try {
            $url = rtrim($this->apiUrl, '/') . '/' . ltrim($endpoint, '/');
            
            $request = Http::withHeaders($this->getHeaders())
                ->timeout($this->timeout);

            switch (strtoupper($method)) {
                case 'GET':
                    $response = $request->get($url, $params);
                    break;
                case 'POST':
                    $response = $request->post($url, $params);
                    break;
                case 'PUT':
                    $response = $request->put($url, $params);
                    break;
                case 'DELETE':
                    $response = $request->delete($url, $params);
                    break;
                default:
                    throw new \Exception("Unsupported HTTP method: {$method}");
            }

            if (!$response->successful()) {
                $statusCode = $response->status();
                $errorBody = $response->body();
                
                Log::error("Carrier API request failed", [
                    'method' => $method,
                    'url' => $url,
                    'status' => $statusCode,
                    'response' => $errorBody,
                ]);

                throw new \Exception(
                    "API request failed with status {$statusCode}: {$errorBody}"
                );
            }

            $responseData = $response->json();
            
            // Поддерживаем различные форматы ответов
            // Формат 1: {"data": [...]}
            // Формат 2: [...] (прямой массив)
            // Формат 3: {"result": [...]}
            return $responseData['data'] 
                ?? $responseData['result'] 
                ?? $responseData 
                ?? [];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error("Carrier API connection error", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw new \Exception("Failed to connect to carrier API: " . $e->getMessage());
        } catch (\Exception $e) {
            Log::error("Carrier API request error", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Получить список станций из API перевозчика
     */
    public function getStations(): array
    {
        return $this->makeRequest('GET', '/stations');
    }

    /**
     * Получить станции отправления от конкретной станции
     */
    public function getStationsFrom(int $stationId): array
    {
        return $this->makeRequest('GET', "/stations/from/{$stationId}");
    }

    /**
     * Синхронизировать станции с API
     */
    public function syncStations(): int
    {
        $stations = $this->getStations();
        $syncedCount = 0;

        foreach ($stations as $stationData) {
            // API может возвращать станции в разных форматах
            // Поддерживаем id, code, name как идентификаторы
            $identifier = $stationData['id'] ?? $stationData['code'] ?? $stationData['name'] ?? null;
            
            if (!$identifier) {
                Log::warning("Station without identifier skipped", [
                    'station_data' => $stationData,
                ]);
                continue;
            }

            // Определяем ключ для поиска существующей записи
            $searchBy = [];
            if (isset($stationData['id'])) {
                $searchBy['code'] = (string)$stationData['id'];
            } elseif (isset($stationData['code'])) {
                $searchBy['code'] = $stationData['code'];
            } else {
                $searchBy['name'] = $stationData['name'] ?? 'Unknown';
            }

            Station::updateOrCreate(
                $searchBy,
                [
                    'name' => $stationData['name'] 
                        ?? $stationData['title'] 
                        ?? $stationData['station_name'] 
                        ?? 'Unknown',
                    'code' => (string)($stationData['id'] 
                        ?? $stationData['code'] 
                        ?? $stationData['station_id'] 
                        ?? ''),
                    'city' => $stationData['city'] 
                        ?? $stationData['city_name'] 
                        ?? $stationData['settlement'] 
                        ?? null,
                    'region' => $stationData['region'] 
                        ?? $stationData['region_name'] 
                        ?? 'Сахалинская область',
                    'latitude' => $stationData['latitude'] 
                        ?? $stationData['lat'] 
                        ?? $stationData['coord_lat'] 
                        ?? null,
                    'longitude' => $stationData['longitude'] 
                        ?? $stationData['lng'] 
                        ?? $stationData['lon'] 
                        ?? $stationData['coord_lng'] 
                        ?? null,
                    'is_active' => $stationData['is_active'] 
                        ?? $stationData['active'] 
                        ?? true,
                ]
            );

            $syncedCount++;
        }

        Log::info("Synced {$syncedCount} stations from carrier API");

        return $syncedCount;
    }

    /**
     * Получить список рейсов (races) на определенную дату
     * API использует /races?from={id}&to={id}&date={d.m.y}
     */
    public function getRaces(int $fromStationId, int $toStationId, string $date): array
    {
        $formattedDate = $this->formatDateForApi($date);
        
        return $this->makeRequest('GET', '/races', [
            'from' => $fromStationId,
            'to' => $toStationId,
            'date' => $formattedDate,
        ]);
    }

    /**
     * Получить информацию о конкретном рейсе
     * API: GET /races/{id}?from={id}&to={id}
     */
    public function getRace(int $raceId, int $fromStationId, int $toStationId): array
    {
        return $this->makeRequest('GET', "/races/{$raceId}", [
            'from' => $fromStationId,
            'to' => $toStationId,
        ]);
    }

    /**
     * Получить список рейсов на определенную дату (legacy метод для совместимости)
     * @deprecated Используйте getRaces()
     */
    public function getTrips(int $routeId, string $date): array
    {
        // Для обратной совместимости, но лучше использовать getRaces()
        Log::warning("Using deprecated getTrips() method, use getRaces() instead");
        return [];
    }

    /**
     * Получить список пассажиров для рейса
     * TODO: Уточнить endpoint для пассажиров в реальном API
     */
    public function getPassengers(string $tripExternalId): array
    {
        // Возможно, endpoint будет /races/{id}/passengers или /races/{id}/passengers?from={id}&to={id}
        // Пока возвращаем пустой массив, нужно уточнить у перевозчика
        Log::warning("getPassengers() endpoint needs to be confirmed with carrier API");
        return $this->makeRequest('GET', "/races/{$tripExternalId}/passengers");
    }

    /**
     * Синхронизировать пассажиров для конкретного рейса
     */
    public function syncPassengers(Trip $trip): int
    {
        if (!$trip->external_id) {
            throw new \Exception('Trip has no external_id');
        }

        $passengersData = $this->getPassengers($trip->external_id);
        $syncedCount = 0;

        foreach ($passengersData as $passengerData) {
            Passenger::updateOrCreate(
                [
                    'trip_id' => $trip->id,
                    'external_booking_id' => $passengerData['booking_id'] 
                        ?? $passengerData['external_booking_id'] 
                        ?? $passengerData['id'] 
                        ?? $passengerData['ticket_id']
                        ?? null,
                ],
                [
                    'first_name' => $passengerData['first_name'] 
                        ?? $passengerData['name'] 
                        ?? $passengerData['fname']
                        ?? '',
                    'last_name' => $passengerData['last_name'] 
                        ?? $passengerData['surname'] 
                        ?? $passengerData['lname']
                        ?? '',
                    'middle_name' => $passengerData['middle_name'] 
                        ?? $passengerData['patronymic'] 
                        ?? $passengerData['mname']
                        ?? null,
                    'email' => $passengerData['email'] 
                        ?? $passengerData['e_mail'] 
                        ?? $passengerData['mail']
                        ?? null,
                    'phone' => $passengerData['phone'] 
                        ?? $passengerData['phone_number'] 
                        ?? $passengerData['tel']
                        ?? null,
                    'seat_number' => $passengerData['seat_number'] 
                        ?? $passengerData['seat'] 
                        ?? $passengerData['place']
                        ?? null,
                    'ticket_price' => $passengerData['price'] 
                        ?? $passengerData['ticket_price'] 
                        ?? $passengerData['cost']
                        ?? null,
                    'ticket_status' => $this->normalizeTicketStatus(
                        $passengerData['status'] 
                            ?? $passengerData['ticket_status'] 
                            ?? 'booked'
                    ),
                ]
            );

            $syncedCount++;
        }

        Log::info("Synced {$syncedCount} passengers for trip {$trip->id} from carrier API");

        return $syncedCount;
    }

    /**
     * Нормализовать статус билета к стандартному формату
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
     * Проверить доступность API
     */
    public function checkConnection(): bool
    {
        try {
            // Попробуем запросить список станций для проверки соединения
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(5)
                ->get(rtrim($this->apiUrl, '/') . '/stations');

            return $response->successful();

        } catch (\Exception $e) {
            Log::warning("Carrier API connection check failed", [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Получить информацию о маршруте
     * TODO: Проверить, есть ли такой endpoint в API
     */
    public function getRoute(int $routeId): array
    {
        Log::warning("getRoute() endpoint may not exist in carrier API");
        return $this->makeRequest('GET', "/routes/{$routeId}");
    }

    /**
     * Получить список маршрутов
     * TODO: Проверить, есть ли такой endpoint в API
     */
    public function getRoutes(array $filters = []): array
    {
        Log::warning("getRoutes() endpoint may not exist in carrier API");
        return $this->makeRequest('GET', '/routes', $filters);
    }
}


