<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Station;
use App\Models\SearchHistory;
use App\Services\CarrierApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TripController extends Controller
{
    protected CarrierApiService $carrierApiService;

    public function __construct(CarrierApiService $carrierApiService)
    {
        $this->carrierApiService = $carrierApiService;
    }

    /**
     * Получить список ВСЕХ рейсов из API перевозчика
     * GET /api/races/all?from={id_from}&to={id_to}&date={Y-m-d}
     */
    public function getAll(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|exists:stations,id',
            'to' => 'required|exists:stations,id',
            'date' => 'required|date',
        ]);

        try {
            $fromStation = Station::findOrFail($request->input('from'));
            $toStation = Station::findOrFail($request->input('to'));

            $fromHasExternalId = !empty($fromStation->external_id) && $fromStation->external_id !== '0';
            $toHasExternalId = !empty($toStation->external_id) && $toStation->external_id !== '0';
            
            if (!$fromHasExternalId || !$toHasExternalId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Станции должны иметь external_id. Пожалуйста, сначала синхронизируйте станции.',
                ], 400);
            }

            // Получаем ВСЕ рейсы (не фильтруем по active)
            $races = $this->carrierApiService->getRaces(
                (int)$fromStation->external_id,
                (int)$toStation->external_id,
                $request->input('date')
            );

            // Получаем external_id всех рейсов для загрузки статистики по пассажирам
            $raceExternalIds = array_map(function ($race) {
                return (string)($race['id'] ?? $race['id_route'] ?? '');
            }, $races);
            $raceExternalIds = array_filter($raceExternalIds);

            // Загружаем статистику по источникам покупки для всех рейсов одним запросом
            $purchaseStats = [];
            if (!empty($raceExternalIds)) {
                $stats = \App\Models\Passenger::whereIn('external_race_id', $raceExternalIds)
                    ->whereNotNull('purchase_source')
                    ->selectRaw('external_race_id, purchase_source, COUNT(*) as count')
                    ->groupBy('external_race_id', 'purchase_source')
                    ->get()
                    ->groupBy('external_race_id');

                foreach ($stats as $raceId => $sources) {
                    $purchaseStats[$raceId] = [
                        'website' => 0,
                        'vk_app' => 0,
                        'mobile_app' => 0,
                        'driver' => 0,
                        'cashier' => 0,
                        'total' => 0,
                    ];
                    foreach ($sources as $source) {
                        $sourceName = $source->purchase_source;
                        if (isset($purchaseStats[$raceId][$sourceName])) {
                            $purchaseStats[$raceId][$sourceName] = (int)$source->count;
                        }
                        $purchaseStats[$raceId]['total'] += (int)$source->count;
                    }
                }
            }

            $enrichedRaces = array_map(function ($race) use ($fromStation, $toStation, $purchaseStats) {
                $raceExternalId = (string)($race['id'] ?? $race['id_route'] ?? '');
                $stats = $purchaseStats[$raceExternalId] ?? [
                    'website' => 0,
                    'vk_app' => 0,
                    'mobile_app' => 0,
                    'driver' => 0,
                    'cashier' => 0,
                    'total' => 0,
                ];

                $enriched = array_merge($race, [
                    'from_station_id' => $fromStation->id,
                    'to_station_id' => $toStation->id,
                    'from_station_name' => $race['route_start'] ?? $race['from_name'] ?? $fromStation->name,
                    'to_station_name' => $race['route_end'] ?? $race['to_name'] ?? $toStation->name,
                    'purchase_stats' => $stats,
                ]);

                // Определяем систему из поля provider
                if (isset($race['provider'])) {
                    $enriched['system'] = $race['provider'];
                }

                // Количество мест и проданных билетов из API
                $enriched['total_seats'] = $race['sits_count'] ?? null;
                $enriched['sold_tickets'] = $race['tkt_count'] ?? null;

                return $enriched;
            }, $races);

            return response()->json([
                'success' => true,
                'data' => array_values($enrichedRaces),
                'count' => count($enrichedRaces),
                'from_station' => [
                    'id' => $fromStation->id,
                    'name' => $fromStation->name,
                    'external_id' => $fromStation->external_id,
                ],
                'to_station' => [
                    'id' => $toStation->id,
                    'name' => $toStation->name,
                    'external_id' => $toStation->external_id,
                ],
                'date' => $request->input('date'),
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get all races", [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить список отмененных рейсов из API перевозчика
     * GET /races?from={id_from}&to={id_to}&date={DD.MM.YY}
     * Фильтрация: active = false
     */
    public function getCancelled(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|exists:stations,id', // ID станции в локальной БД
            'to' => 'required|exists:stations,id',   // ID станции в локальной БД
            'date' => 'required|date',               // Дата в формате Y-m-d
        ]);

        try {
            // Получаем станции из локальной БД
            $fromStation = Station::findOrFail($request->input('from'));
            $toStation = Station::findOrFail($request->input('to'));

            // Проверяем наличие external_id (проверяем на null, пустую строку, "0" и false)
            $fromHasExternalId = !empty($fromStation->external_id) && $fromStation->external_id !== '0';
            $toHasExternalId = !empty($toStation->external_id) && $toStation->external_id !== '0';
            
            if (!$fromHasExternalId || !$toHasExternalId) {
                $missingStations = [];
                if (!$fromHasExternalId) {
                    $missingStations[] = $fromStation->name . ' (ID: ' . $fromStation->id . ', external_id: ' . ($fromStation->external_id ?? 'null') . ')';
                }
                if (!$toHasExternalId) {
                    $missingStations[] = $toStation->name . ' (ID: ' . $toStation->id . ', external_id: ' . ($toStation->external_id ?? 'null') . ')';
                }
                
                Log::warning("Stations missing external_id", [
                    'from_station' => [
                        'id' => $fromStation->id,
                        'name' => $fromStation->name,
                        'external_id' => $fromStation->external_id,
                    ],
                    'to_station' => [
                        'id' => $toStation->id,
                        'name' => $toStation->name,
                        'external_id' => $toStation->external_id,
                    ],
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Станции должны иметь external_id. Пожалуйста, сначала синхронизируйте станции.',
                    'details' => 'Следующие станции не синхронизированы: ' . implode(', ', $missingStations),
                    'hint' => 'Перейдите в админ-панель → Настройки → API Перевозчика и нажмите кнопку "Обновить станции"',
                ], 400);
            }

            // Получаем отмененные рейсы из API перевозчика
            $cancelledRaces = $this->carrierApiService->getCancelledRaces(
                (int)$fromStation->external_id,
                (int)$toStation->external_id,
                $request->input('date')
            );

            $this->logSearchHistory($request, $fromStation, $toStation, $cancelledRaces);

            // Добавляем информацию о станциях к каждому рейсу
            // чтобы при добавлении в задачу можно было создать Trip
            // Также нормализуем поля: route -> route_number, route_start -> from_station_name, route_end -> to_station_name
            $enrichedRaces = array_map(function ($race) use ($fromStation, $toStation) {
                $enriched = array_merge($race, [
                    'from_station_id' => $fromStation->id,
                    'to_station_id' => $toStation->id,
                    'from_station_name' => $race['route_start'] ?? $fromStation->name,
                    'to_station_name' => $race['route_end'] ?? $toStation->name,
                ]);
                
                // Нормализуем поле route -> route_number для единообразия
                if (isset($race['route']) && !isset($enriched['route_number'])) {
                    $enriched['route_number'] = $race['route'];
                }
                
                // Если есть route_start и route_end в API, используем их
                if (isset($race['route_start'])) {
                    $enriched['from_station_name'] = $race['route_start'];
                }
                if (isset($race['route_end'])) {
                    $enriched['to_station_name'] = $race['route_end'];
                }
                
                return $enriched;
            }, $cancelledRaces);

            // Возвращаем данные рейсов в формате согласно ТЗ
            // Структура ответа API рейсов:
            // - id (string) - Уникальный идентификатор рейса
            // - active (boolean) - Статус рейса (false = отменен)
            // - route_tz (integer) - Часовой пояс маршрута (UTC+N)
            // - dt_depart (datetime) - Время отправления (UTC)
            // - dt_arrive (datetime) - Время прибытия (UTC)
            // - from_station_id (integer) - ID станции отправления в локальной БД
            // - to_station_id (integer) - ID станции прибытия в локальной БД
            // - from_station_name (string) - Название станции отправления
            // - to_station_name (string) - Название станции прибытия

            return response()->json([
                'success' => true,
                'data' => array_values($enrichedRaces), // array_values для сброса индексов после фильтрации
                'count' => count($enrichedRaces),
                'from_station' => [
                    'id' => $fromStation->id,
                    'name' => $fromStation->name,
                    'external_id' => $fromStation->external_id,
                ],
                'to_station' => [
                    'id' => $toStation->id,
                    'name' => $toStation->name,
                    'external_id' => $toStation->external_id,
                ],
                'date' => $request->input('date'),
            ]);

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Определяем HTTP статус код на основе типа ошибки
            $httpStatus = 500;
            
            // Если ошибка содержит информацию о 4xx статусе, используем соответствующий код
            if (strpos($errorMessage, 'HTTP 400') !== false) {
                $httpStatus = 400;
            } elseif (strpos($errorMessage, 'HTTP 401') !== false) {
                $httpStatus = 401;
            } elseif (strpos($errorMessage, 'HTTP 403') !== false) {
                $httpStatus = 403;
            } elseif (strpos($errorMessage, 'HTTP 404') !== false) {
                $httpStatus = 404;
            } elseif (strpos($errorMessage, 'HTTP 422') !== false) {
                $httpStatus = 422;
            } elseif (strpos($errorMessage, 'HTTP 429') !== false) {
                $httpStatus = 429;
            }

            Log::error("Failed to get cancelled races", [
                'request' => [
                    'from' => $request->input('from'),
                    'to' => $request->input('to'),
                    'date' => $request->input('date'),
                ],
                'error' => $errorMessage,
                'http_status' => $httpStatus,
                'trace' => $e->getTraceAsString(),
            ]);

            // Формируем понятное сообщение для пользователя
            $userMessage = $errorMessage;
            
            // Улучшаем сообщения для типичных случаев
            if (strpos($errorMessage, 'API key is not configured') !== false) {
                $userMessage = 'API ключ не настроен. Пожалуйста, настройте CARRIER_API_KEY в админ-панели или .env файле.';
            } elseif (strpos($errorMessage, 'API URL is not configured') !== false) {
                $userMessage = 'URL API не настроен. Пожалуйста, настройте CARRIER_API_URL в админ-панели или .env файле.';
            } elseif (strpos($errorMessage, 'Не удалось подключиться') !== false) {
                $userMessage = $errorMessage . ' Проверьте, что сервер API доступен и настройки сети корректны.';
            }

            return response()->json([
                'success' => false,
                'message' => $userMessage,
                'error_code' => $httpStatus,
            ], $httpStatus);
        }
    }

    /**
     * Получить информацию о конкретном рейсе
     */
    public function show(int $id): JsonResponse
    {
        $trip = Trip::with([
            'route.departureStation',
            'route.arrivalStation',
            'passengers'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $trip,
        ]);
    }

    /**
     * Получить информацию о рейсе по external_id
     * GET /api/races/{externalId}
     */
    public function getByExternalId(string $externalId): JsonResponse
    {
        try {
            // Сначала пытаемся найти в локальной БД
            $trip = Trip::where('external_id', $externalId)
                ->with([
                    'route.departureStation',
                    'route.arrivalStation',
                    'passengers',
                    'drivers'
                ])
                ->first();

            if ($trip) {
                return response()->json([
                    'success' => true,
                    'data' => $trip,
                    'source' => 'database',
                ]);
            }

            // Если не найден в БД, возвращаем только external_id
            // Данные рейса будут получены на фронтенде из списка рейсов
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $externalId,
                    'external_id' => $externalId,
                ],
                'source' => 'api',
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get trip by external_id", [
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get trip: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Обновить статус рейса
     * PUT /api/trips/{id}/status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:scheduled,cancelled,delayed,completed',
            'reason' => 'nullable|string|max:255',
            'new_departure_at' => 'nullable|date',
        ]);

        try {
            $trip = Trip::findOrFail($id);

            // Используем метод changeStatus для сохранения истории
            $trip->changeStatus(
                $validated['status'],
                $validated['reason'] ?? null,
                $validated['new_departure_at'] ?? null,
                auth()->id()
            );

            // Загружаем обновленные данные с историей изменений
            $trip->load('statusChanges.changedBy');

            return response()->json([
                'success' => true,
                'data' => $trip,
                'message' => 'Статус рейса успешно обновлен',
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to update trip status", [
                'trip_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении статуса рейса: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function logSearchHistory(Request $request, Station $fromStation, Station $toStation, array $races): void
    {
        try {
            $userId = $request->user()?->id;
            $tripDate = $request->input('date');
            $cancelledCount = count($races);

            SearchHistory::create([
                'user_id' => $userId,
                'from_station_id' => $fromStation->id,
                'to_station_id' => $toStation->id,
                'from_station_name' => $fromStation->name,
                'to_station_name' => $toStation->name,
                'trip_date' => $tripDate,
                'cancelled_count' => $cancelledCount,
                'result_count' => $cancelledCount,
                'search_type' => 'cancelled',
                'query_payload' => [
                    'from_station_external_id' => $fromStation->external_id,
                    'to_station_external_id' => $toStation->external_id,
                    'route_timezone' => $races[0]['route_tz'] ?? null,
                    'request_date' => $tripDate,
                ],
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log search history', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}


