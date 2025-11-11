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
    protected string $apiUrl = '';
    protected string $apiKey = '';
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
                $headers = $this->getHeaders();
                $sanitizedHeaders = $headers;
                if (isset($sanitizedHeaders['x-access-token'])) {
                    $token = $sanitizedHeaders['x-access-token'];
                    $sanitizedHeaders['x-access-token'] = substr($token, 0, 20) . '...' . substr($token, -10) . ' (length: ' . strlen($token) . ')';
                }
                
                Log::info("Carrier API request", [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'url' => $url,
                    'headers' => $sanitizedHeaders,
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
            
            // Сначала получаем список всех станций БД без external_id для последующего сопоставления
            $stationsWithoutExternalId = Station::where(function($query) {
                $query->whereNull('external_id')
                    ->orWhere('external_id', '')
                    ->orWhere('external_id', '0');
            })->get();
            
            Log::info("Stations without external_id before sync", [
                'count' => $stationsWithoutExternalId->count(),
                'stations' => $stationsWithoutExternalId->map(function($s) {
                    return ['id' => $s->id, 'name' => $s->name];
                })->toArray(),
            ]);

        foreach ($stations as $stationData) {
            // Получаем external_id из ответа API (это ID станции из внешнего API)
            $externalId = $stationData['id'] ?? $stationData['external_id'] ?? null;
            
            if (empty($externalId) || $externalId === '0' || $externalId === 0) {
                Log::warning("Station without valid external_id skipped", [
                    'station_data' => $stationData,
                    'external_id' => $externalId,
                ]);
                continue;
            }

            $externalId = (string)$externalId; // Приводим к строке для консистентности
            
            // Получаем название станции для поиска
            $stationName = $stationData['name'] 
                ?? $stationData['title'] 
                ?? $stationData['station_name'] 
                ?? null;

            // Ищем станцию по external_id, если не найдено - по имени (для обновления существующих станций без external_id)
            $station = Station::where('external_id', $externalId)->first();
            
            // Если не найдено по external_id, ищем по имени (для обновления существующих станций)
            if (!$station && $stationName) {
                // Нормализуем название из API для сравнения
                $normalizedName = trim(mb_strtolower($stationName));
                
                // Сначала точное совпадение
                $station = Station::where('name', $stationName)->first();
                
                // Если не найдено, ищем по нормализованному названию (на случай небольших различий в пробелах/регистре)
                if (!$station) {
                    $station = Station::whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])->first();
                }
                
                // Если все еще не найдено, ищем по частичному совпадению (API название содержит название из БД)
                // Например: "Южно-Сахалинск" в БД и "Южно-Сахалинск, ЖД Вокзал" в API
                if (!$station) {
                    // Используем список станций без external_id, полученный в начале синхронизации
                    // Но фильтруем только те, которые все еще не имеют external_id (на случай, если они были обновлены)
                    $availableStationsWithoutExternalId = $stationsWithoutExternalId->filter(function($s) {
                        return empty($s->external_id) || $s->external_id === '0' || $s->external_id === '';
                    });
                    
                    Log::debug("Searching for stations without external_id", [
                        'api_name' => $stationName,
                        'normalized_api' => $normalizedName,
                        'available_stations_count' => $availableStationsWithoutExternalId->count(),
                        'stations_list' => $availableStationsWithoutExternalId->map(function($s) {
                            return ['id' => $s->id, 'name' => $s->name, 'external_id' => $s->external_id];
                        })->toArray(),
                    ]);
                    
                    foreach ($availableStationsWithoutExternalId as $dbStation) {
                        // Проверяем, что станция все еще не имеет external_id (могла быть обновлена в этой же итерации)
                        if (!empty($dbStation->external_id) && $dbStation->external_id !== '0' && $dbStation->external_id !== '') {
                            continue;
                        }
                        
                        $dbStationName = trim(mb_strtolower($dbStation->name));
                        
                        // Проверяем, содержит ли название из API название из БД
                        // Например: "южно-сахалинск, жд вокзал" содержит "южно-сахалинск"
                        // Используем mb_strpos для корректной работы с кириллицей
                        $position = mb_strpos($normalizedName, $dbStationName);
                        $contains = $position !== false;
                        
                        Log::debug("Checking station match", [
                            'api_name' => $stationName,
                            'db_name' => $dbStation->name,
                            'normalized_api' => $normalizedName,
                            'normalized_db' => $dbStationName,
                            'contains' => $contains,
                            'position' => $position,
                            'db_name_length' => mb_strlen($dbStationName),
                        ]);
                        
                        if ($contains) {
                            Log::debug("Found potential match", [
                                'api_name' => $stationName,
                                'db_name' => $dbStation->name,
                                'normalized_api' => $normalizedName,
                                'normalized_db' => $dbStationName,
                                'db_name_length' => mb_strlen($dbStationName),
                                'position' => $position,
                            ]);
                            
                            // Дополнительная проверка: название из БД должно быть достаточно длинным
                            // Минимум 5 символов для коротких названий, чтобы избежать ложных совпадений
                            // Используем mb_strlen для корректной работы с кириллицей
                            $dbNameLength = mb_strlen($dbStationName);
                            if ($dbNameLength >= 5) {
                                $station = $dbStation;
                                Log::info("Station found by partial name match (reverse search)", [
                                    'found_name' => $station->name,
                                    'api_name' => $stationName,
                                    'external_id' => $externalId,
                                    'match_type' => 'api_contains_db',
                                    'db_name_length' => $dbNameLength,
                                    'normalized_api' => $normalizedName,
                                    'normalized_db' => $dbStationName,
                                ]);
                                break;
                            } else {
                                Log::debug("Match rejected: db_name too short", [
                                    'db_name' => $dbStation->name,
                                    'db_name_length' => $dbNameLength,
                                ]);
                            }
                        }
                    }
                }
                
                // Если все еще не найдено, ищем по ключевым словам
                if (!$station && mb_strlen($normalizedName) > 5) {
                    // Ищем станции без external_id, которые содержат ключевые слова из названия API
                    $keywords = explode(' ', $normalizedName);
                    $keywords = array_filter($keywords, function($word) {
                        return mb_strlen($word) > 3; // Только слова длиннее 3 символов
                    });
                    
                    if (!empty($keywords)) {
                        // Используем список станций без external_id, полученный в начале синхронизации
                        $availableStationsWithoutExternalId = $stationsWithoutExternalId->filter(function($s) {
                            return empty($s->external_id) || $s->external_id === '0' || $s->external_id === '';
                        });
                        
                        foreach ($availableStationsWithoutExternalId as $dbStation) {
                            // Проверяем, что станция все еще не имеет external_id
                            if (!empty($dbStation->external_id) && $dbStation->external_id !== '0' && $dbStation->external_id !== '') {
                                continue;
                            }
                            
                            $dbStationName = mb_strtolower($dbStation->name);
                            $matches = 0;
                            
                            foreach ($keywords as $keyword) {
                                if (mb_strpos($dbStationName, $keyword) !== false) {
                                    $matches++;
                                }
                            }
                            
                            // Если хотя бы одно ключевое слово совпадает
                            if ($matches > 0 && $matches >= count($keywords) * 0.5) {
                                $station = $dbStation;
                                Log::info("Station found by keyword match", [
                                    'found_name' => $station->name,
                                    'api_name' => $stationName,
                                    'external_id' => $externalId,
                                    'matches' => $matches,
                                    'keywords' => $keywords,
                                ]);
                                break;
                            }
                        }
                    }
                }
                
                // Логируем, если станция все еще не найдена
                if (!$station) {
                    Log::warning("Station not found in database during sync", [
                        'api_name' => $stationName,
                        'external_id' => $externalId,
                        'hint' => 'Station will be created as new',
                    ]);
                }
            }

            // Подготавливаем данные для обновления/создания
            $stationDataToSave = [
                'external_id' => $externalId,
                'name' => $stationName ?? 'Unknown',
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
            ];

            if ($station) {
                // Обновляем существующую станцию
                $station->update($stationDataToSave);
                Log::debug("Updated station", [
                    'id' => $station->id,
                    'name' => $station->name,
                    'external_id' => $station->external_id,
                ]);
            } else {
                // Создаем новую станцию
                $station = Station::create($stationDataToSave);
                Log::debug("Created station", [
                    'id' => $station->id,
                    'name' => $station->name,
                    'external_id' => $station->external_id,
                ]);
            }

            $syncedCount++;
        }

        // Проверяем, остались ли станции без external_id и удаляем их
        $stationsWithoutExternalId = Station::where(function($query) {
            $query->whereNull('external_id')
                ->orWhere('external_id', '')
                ->orWhere('external_id', '0');
        })->get();
        
        $deletedCount = 0;
        $skippedCount = 0;
        
        foreach ($stationsWithoutExternalId as $station) {
            // Проверяем, используется ли станция в маршрутах
            $usedInRoutes = \App\Models\Route::where(function($query) use ($station) {
                $query->where('departure_station_id', $station->id)
                    ->orWhere('arrival_station_id', $station->id);
            })->exists();
            
            if ($usedInRoutes) {
                // Станция используется в маршрутах, не удаляем
                $skippedCount++;
                Log::warning("Station without external_id skipped (used in routes)", [
                    'station_id' => $station->id,
                    'station_name' => $station->name,
                ]);
            } else {
                // Станция не используется, можно удалить
                $station->delete();
                $deletedCount++;
                Log::info("Station without external_id deleted", [
                    'station_id' => $station->id,
                    'station_name' => $station->name,
                ]);
            }
        }
        
        if ($stationsWithoutExternalId->count() > 0) {
            Log::info("Stations without external_id processed", [
                'total' => $stationsWithoutExternalId->count(),
                'deleted' => $deletedCount,
                'skipped' => $skippedCount,
                'reason_skipped' => 'used in routes',
            ]);
        }

        // Подсчитываем общее количество станций в БД после удаления
        $totalStationsInDb = Station::count();
        
        // Подсчитываем оставшиеся станции без external_id (если они были пропущены из-за использования в маршрутах)
        $remainingStationsWithoutExternalId = Station::where(function($query) {
            $query->whereNull('external_id')
                ->orWhere('external_id', '')
                ->orWhere('external_id', '0');
        })->count();
        
        Log::info("Synced {$syncedCount} stations from carrier API", [
            'total_synced' => $syncedCount,
            'stations_deleted' => $deletedCount,
            'stations_skipped' => $skippedCount,
            'remaining_stations_without_external_id' => $remainingStationsWithoutExternalId,
            'total_stations_in_db' => $totalStationsInDb,
            'stations_from_api' => count($stations),
        ]);
        
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


