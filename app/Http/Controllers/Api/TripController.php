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

            // Проверяем наличие external_id
            if (!$fromStation->external_id || !$toStation->external_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stations must have external_id. Please sync stations first.',
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
            Log::error("Failed to get cancelled races", [
                'request' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get cancelled races: ' . $e->getMessage(),
            ], 500);
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


