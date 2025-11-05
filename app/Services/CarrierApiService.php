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
        // Берем настройки ТОЛЬКО из БД, без fallback на config/env
        $this->apiUrl = $this->getSetting('url');
        $this->apiKey = $this->getSetting('key');
        $this->timeout = (int) ($this->getSetting('timeout', 60)); // По умолчанию 60 секунд для больших запросов
    }

    /**
     * Получить настройку из БД
     * @throws \Exception если настройка обязательна и не найдена
     */
    protected function getSetting(string $key, $default = null)
    {
        try {
            $fullKey = "carrier_api_{$key}";
            $setting = \App\Models\Setting::get($fullKey);
            return $setting !== null ? $setting : $default;
        } catch (\Exception $e) {
            Log::warning("Failed to get carrier API setting from DB", [
                'key' => $fullKey,
                'error' => $e->getMessage(),
            ]);
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
     * Выполнить HTTP запрос с обработкой ошибок и retry механизмом
     * 
     * @param string $method HTTP метод
     * @param string $endpoint Endpoint API
     * @param array $params Параметры запроса
     * @param int $maxRetries Максимальное количество повторов для временных ошибок
     * @return array
     * @throws \Exception
     */
    protected function makeRequest(string $method, string $endpoint, array $params = [], int $maxRetries = 2): array
    {
        $url = rtrim($this->apiUrl, '/') . '/' . ltrim($endpoint, '/');
        $attempt = 0;
        
        while ($attempt <= $maxRetries) {
            try {
                // Логируем запрос (без секретных данных)
                Log::info("Carrier API request", [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'params' => $this->sanitizeParams($params),
                    'attempt' => $attempt + 1,
                ]);

                // Проверяем наличие обязательных данных
                if (empty($this->apiKey)) {
                    throw new \Exception("API ключ не настроен. Пожалуйста, настройте API ключ в админ-панели (раздел 'API Перевозчика').");
                }

                if (empty($this->apiUrl)) {
                    throw new \Exception("API URL не настроен. Пожалуйста, настройте API URL в админ-панели (раздел 'API Перевозчика').");
                }
                
                // Настраиваем HTTP клиент с правильными параметрами
                $request = Http::withHeaders($this->getHeaders())
                    ->timeout($this->timeout) // Таймаут из настроек БД
                    ->connectTimeout(10) // Таймаут подключения 10 секунд
                    ->retry(2, 100); // 2 попытки с задержкой 100ms для временных ошибок

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

                // Обработка различных статус кодов
                if ($response->successful()) {
                    $responseData = $response->json();
                    
                    // Валидация ответа
                    if ($responseData === null) {
                        // Попробуем получить как текст, если JSON не валиден
                        $body = $response->body();
                        Log::warning("Carrier API returned invalid JSON", [
                            'url' => $url,
                            'response_body' => substr($body, 0, 500), // Первые 500 символов
                            'status' => $response->status(),
                        ]);
                        
                        // Если тело пустое, возвращаем пустой массив
                        if (empty(trim($body))) {
                            return [];
                        }
                        
                        throw new \Exception("Неверный формат ответа от API перевозчика. Ожидается JSON.");
                    }
                    
                    // Поддерживаем различные форматы ответов
                    // API может вернуть массив напрямую или объект с полем data/result
                    if (is_array($responseData)) {
                        // Если это массив, проверяем наличие ключей data или result
                        if (isset($responseData['data']) && is_array($responseData['data'])) {
                            $result = $responseData['data'];
                        } elseif (isset($responseData['result']) && is_array($responseData['result'])) {
                            $result = $responseData['result'];
                        } elseif (isset($responseData[0]) || empty($responseData)) {
                            // Если массив начинается с 0 или пустой, это уже список
                            $result = $responseData;
                        } else {
                            // Если это ассоциативный массив без data/result, возвращаем как есть
                            $result = $responseData;
                        }
                    } else {
                        // Если не массив, пытаемся извлечь данные
                        $result = $responseData['data'] ?? $responseData['result'] ?? [];
                    }
                    
                    // Убеждаемся, что результат - массив
                    if (!is_array($result)) {
                        $result = [];
                    }
                    
                    Log::info("Carrier API request successful", [
                        'endpoint' => $endpoint,
                        'response_count' => count($result),
                        'response_type' => gettype($responseData),
                    ]);
                    
                    return $result;
                }

                // Обработка ошибок по статус кодам
                $statusCode = $response->status();
                $errorBody = $response->body();
                $errorJson = $response->json();
                
                $errorMessage = $this->getErrorMessage($statusCode, $errorBody, $errorJson);
                
                Log::error("Carrier API request failed", [
                    'method' => $method,
                    'url' => $url,
                    'params' => $this->sanitizeParams($params),
                    'status' => $statusCode,
                    'response_body' => $errorBody,
                    'attempt' => $attempt + 1,
                ]);

                // Для временных ошибок (5xx) - повторяем попытку
                if ($statusCode >= 500 && $statusCode < 600 && $attempt < $maxRetries) {
                    $delay = (int) pow(2, $attempt) * 100; // Exponential backoff: 100ms, 200ms, 400ms
                    Log::warning("Temporary error, retrying...", [
                        'attempt' => $attempt + 1,
                        'delay_ms' => $delay,
                    ]);
                    usleep($delay * 1000); // Задержка в микросекундах
                    $attempt++;
                    continue;
                }

                // Для клиентских ошибок (4xx) - не повторяем
                throw new \Exception($errorMessage);

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Ошибка подключения - повторяем для временных сетевых проблем
                if ($attempt < $maxRetries) {
                    $delay = (int) pow(2, $attempt) * 100;
                    Log::warning("Connection error, retrying...", [
                        'attempt' => $attempt + 1,
                        'delay_ms' => $delay,
                        'error' => $e->getMessage(),
                    ]);
                    usleep($delay * 1000);
                    $attempt++;
                    continue;
                }
                
                Log::error("Carrier API connection error", [
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'attempts' => $attempt + 1,
                ]);
                
                throw new \Exception("Не удалось подключиться к API перевозчика. Проверьте доступность сервера и настройки сети.");
                
            } catch (\Illuminate\Http\Client\RequestException $e) {
                // Ошибка запроса
                Log::error("Carrier API request exception", [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt + 1,
                ]);
                throw new \Exception("Ошибка запроса к API перевозчика: " . $e->getMessage());
                
            } catch (\Exception $e) {
                Log::error("Carrier API unexpected error", [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        }

        // Если дошли сюда - все попытки исчерпаны
        throw new \Exception("Не удалось выполнить запрос к API перевозчика после " . ($maxRetries + 1) . " попыток");
    }

    /**
     * Получить понятное сообщение об ошибке на основе статус кода
     */
    protected function getErrorMessage(int $statusCode, string $errorBody, ?array $errorJson): string
    {
        $defaultMessage = "API вернул ошибку (HTTP {$statusCode})";
        
        // Пытаемся извлечь сообщение из JSON ответа
        if ($errorJson && isset($errorJson['message'])) {
            return $errorJson['message'];
        }
        
        if ($errorJson && isset($errorJson['error'])) {
            return is_string($errorJson['error']) ? $errorJson['error'] : $defaultMessage;
        }

        // Специфичные сообщения для разных статус кодов
        switch ($statusCode) {
            case 400:
                return "Некорректный запрос (HTTP 400). Проверьте правильность параметров: станции и дата.";
            case 401:
                return "Не авторизован (HTTP 401). Проверьте правильность API ключа (CARRIER_API_KEY).";
            case 403:
                return "Доступ запрещен (HTTP 403). Проверьте права доступа API ключа.";
            case 404:
                return "Ресурс не найден (HTTP 404). Проверьте правильность URL API и endpoint.";
            case 422:
                return "Ошибка валидации (HTTP 422). Проверьте формат данных запроса.";
            case 429:
                return "Превышен лимит запросов (HTTP 429). Попробуйте позже.";
            case 500:
                return "Внутренняя ошибка сервера API (HTTP 500). Попробуйте позже или обратитесь к администратору API.";
            case 502:
            case 503:
            case 504:
                return "Сервис API временно недоступен (HTTP {$statusCode}). Попробуйте позже.";
            default:
                // Пытаемся извлечь полезную информацию из тела ответа
                if (strlen($errorBody) < 500) {
                    return "Ошибка API (HTTP {$statusCode}): " . substr($errorBody, 0, 200);
                }
                return $defaultMessage;
        }
    }

    /**
     * Очистить параметры от чувствительных данных для логирования
     */
    protected function sanitizeParams(array $params): array
    {
        $sanitized = $params;
        // Можно добавить маскирование чувствительных параметров при необходимости
        return $sanitized;
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
     * Использует увеличенный таймаут для больших запросов
     */
    public function syncStations(): int
    {
        // Временно увеличиваем таймаут для синхронизации станций (может быть много данных)
        $originalTimeout = $this->timeout;
        $this->timeout = max($this->timeout, 120); // Минимум 120 секунд для синхронизации
        
        try {
            Log::info("Starting stations synchronization", [
                'api_url' => $this->apiUrl,
                'timeout' => $this->timeout,
            ]);
            
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
        
        } finally {
            // Восстанавливаем оригинальный таймаут
            $this->timeout = $originalTimeout;
        }
    }

    /**
     * Получить список рейсов (races) на определенную дату
     * API: GET /races?from={id_from}&to={id_to}&date={DD.MM.YY}
     * 
     * @param int $fromStationId ID станции отправления (external_id)
     * @param int $toStationId ID станции прибытия (external_id)
     * @param string $date Дата в формате Y-m-d (например: 2025-10-31)
     * @return array Массив рейсов
     * @throws \Exception
     */
    public function getRaces(int $fromStationId, int $toStationId, string $date): array
    {
        // Валидация входных параметров
        if ($fromStationId <= 0) {
            throw new \Exception("Некорректный ID станции отправления: {$fromStationId}");
        }
        
        if ($toStationId <= 0) {
            throw new \Exception("Некорректный ID станции прибытия: {$toStationId}");
        }
        
        if ($fromStationId === $toStationId) {
            throw new \Exception("Станция отправления и прибытия не могут совпадать");
        }
        
        // Валидация и форматирование даты
        try {
            $carbon = \Carbon\Carbon::parse($date);
            $formattedDate = $carbon->format('d.m.y'); // DD.MM.YY
        } catch (\Exception $e) {
            throw new \Exception("Некорректный формат даты: {$date}. Ожидается формат Y-m-d (например: 2025-10-31)");
        }
        
        Log::info("Getting races from carrier API", [
            'from_station_id' => $fromStationId,
            'to_station_id' => $toStationId,
            'date' => $date,
            'formatted_date' => $formattedDate,
        ]);
        
        $races = $this->makeRequest('GET', '/races', [
            'from' => $fromStationId,
            'to' => $toStationId,
            'date' => $formattedDate,
        ]);

        // Валидация ответа
        if (!is_array($races)) {
            Log::warning("Unexpected response format from races API", [
                'response_type' => gettype($races),
                'response' => $races,
            ]);
            return [];
        }

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


