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
        // Сначала берем из .env, затем из БД (настройки), затем из config
        $this->apiUrl = env('CARRIER_API_URL') 
            ?? $this->getSetting('url', config('services.carrier_api.url', 'http://rc.rfbus.ru:8086'));
        $this->apiKey = env('CARRIER_API_KEY') 
            ?? $this->getSetting('key', config('services.carrier_api.key', ''));
        $this->timeout = (int) ($this->getSetting('timeout', config('services.carrier_api.timeout', 30)));
    }

    /**
     * Получить настройку из БД или config
     */
    protected function getSetting(string $key, $default = null)
    {
        try {
            $fullKey = "carrier_api_{$key}";
            $setting = \App\Models\Setting::get($fullKey);
            return $setting !== null ? $setting : $default;
        } catch (\Exception $e) {
            // Если таблица settings еще не создана, используем config
            return $default;
        }
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
     * Конвертировать дату в формат API (DD.MM.YY)
     * Пример: 2024-11-07 -> 07.11.24
     * Пример: 2025-10-31 -> 31.10.25
     */
    protected function formatDateForApi(string $date): string
    {
        try {
            $carbon = Carbon::parse($date);
            return $carbon->format('d.m.y'); // DD.MM.YY с ведущими нулями
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
     * GET http://rc.rfbus.ru:8086/stations
     * Заголовок: x-access-token
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
     * Сохраняет станции с external_id (ID из внешнего API)
     */
    public function syncStations(): int
    {
        $stations = $this->getStations();
        $syncedCount = 0;

        foreach ($stations as $stationData) {
            // Получаем external_id из ответа API (это ID станции из внешнего API)
            $externalId = $stationData['id'] ?? $stationData['external_id'] ?? null;
            
            if (!$externalId) {
                Log::warning("Station without external_id skipped", [
                    'station_data' => $stationData,
                ]);
                continue;
            }

            // Ищем по external_id, если не найдено - по коду
            $searchBy = ['external_id' => (string)$externalId];

            Station::updateOrCreate(
                $searchBy,
                [
                    'external_id' => (string)$externalId,
                    'name' => $stationData['name'] 
                        ?? $stationData['title'] 
                        ?? $stationData['station_name'] 
                        ?? 'Unknown',
                    'code' => (string)($stationData['code'] 
                        ?? $stationData['station_code'] 
                        ?? $externalId),
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
     * API: GET /races?from={id_from}&to={id_to}&date={DD.MM.YY}
     * 
     * @param int $fromStationId ID станции отправления (external_id)
     * @param int $toStationId ID станции прибытия (external_id)
     * @param string $date Дата в формате Y-m-d (например: 2025-10-31)
     * @return array Массив рейсов
     */
    public function getRaces(int $fromStationId, int $toStationId, string $date): array
    {
        $formattedDate = $this->formatDateForApi($date);
        
        $races = $this->makeRequest('GET', '/races', [
            'from' => $fromStationId,
            'to' => $toStationId,
            'date' => $formattedDate,
        ]);

        // Возвращаем все рейсы (фильтрация по active=false будет в контроллере)
        return $races;
    }

    /**
     * Получить список отмененных рейсов (active = false)
     * 
     * @param int $fromStationId ID станции отправления (external_id)
     * @param int $toStationId ID станции прибытия (external_id)
     * @param string $date Дата в формате Y-m-d
     * @return array Массив отмененных рейсов
     */
    public function getCancelledRaces(int $fromStationId, int $toStationId, string $date): array
    {
        $races = $this->getRaces($fromStationId, $toStationId, $date);
        
        // Фильтруем только отмененные рейсы (active = false)
        return array_filter($races, function ($race) {
            return isset($race['active']) && $race['active'] === false;
        });
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


