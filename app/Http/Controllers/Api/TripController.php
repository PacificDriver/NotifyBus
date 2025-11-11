<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\Station;
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

            // Возвращаем данные рейсов в формате согласно ТЗ
            // Структура ответа API рейсов:
            // - id (string) - Уникальный идентификатор рейса
            // - active (boolean) - Статус рейса (false = отменен)
            // - route_tz (integer) - Часовой пояс маршрута (UTC+N)
            // - dt_depart (datetime) - Время отправления (UTC)
            // - dt_arrive (datetime) - Время прибытия (UTC)

            return response()->json([
                'success' => true,
                'data' => array_values($cancelledRaces), // array_values для сброса индексов после фильтрации
                'count' => count($cancelledRaces),
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
}


