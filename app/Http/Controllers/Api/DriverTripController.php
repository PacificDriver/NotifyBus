<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverTripController extends Controller
{
    /**
     * Получить список рейсов текущего водителя
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $driver = $request->user();

        // Получаем рейсы водителя с пагинацией
        $trips = $driver->trips()
            ->with([
                'route.departureStation',
                'route.arrivalStation',
            ])
            ->orderBy('departure_time', 'asc')
            ->paginate(20);

        // Форматируем данные для ответа (минимальная информация)
        $formattedTrips = $trips->map(function ($trip) {
            return [
                'id' => $trip->id,
                'race_id' => $trip->external_id,
                'from_id' => $trip->route->departureStation?->external_id,
                'to_id' => $trip->route->arrivalStation?->external_id,
                'date' => $trip->departure_time?->format('Y-m-d'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedTrips,
            'pagination' => [
                'current_page' => $trips->currentPage(),
                'per_page' => $trips->perPage(),
                'total' => $trips->total(),
                'last_page' => $trips->lastPage(),
            ],
        ]);
    }
}
