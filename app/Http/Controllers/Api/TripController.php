<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    /**
     * Получить список отмененных рейсов
     */
    public function getCancelled(Request $request): JsonResponse
    {
        $request->validate([
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id' => 'required|exists:stations,id',
            'date_from' => 'required|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to', $dateFrom);

        $trips = Trip::with(['route.departureStation', 'route.arrivalStation'])
            ->whereHas('route', function ($query) use ($request) {
                $query->where('departure_station_id', $request->input('departure_station_id'))
                      ->where('arrival_station_id', $request->input('arrival_station_id'));
            })
            ->cancelled()
            ->byDateRange($dateFrom . ' 00:00:00', $dateTo . ' 23:59:59')
            ->orderBy('departure_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $trips,
            'count' => $trips->count(),
        ]);
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


