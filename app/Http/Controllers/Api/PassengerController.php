<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PassengerController extends Controller
{
    /**
     * Получить список пассажиров для конкретного рейса из локальной БД
     */
    public function getByTrip(int $tripId): JsonResponse
    {
        $passengers = Passenger::where('trip_id', $tripId)
            ->with('trip')
            ->get();

        // Фильтруем только тех, кто может получать уведомления
        $validPassengers = $passengers->filter(function ($passenger) {
            return $passenger->canReceiveNotifications();
        });

        return response()->json([
            'success' => true,
            'data' => $validPassengers->values(),
            'total_count' => $passengers->count(),
            'valid_count' => $validPassengers->count(),
            'invalid_count' => $passengers->count() - $validPassengers->count(),
        ]);
    }

    /**
     * Получить пассажиров по external_id рейса из локальной БД
     * GET /api/passengers/by-race/{externalRaceId}
     */
    public function getByRace(string $externalRaceId): JsonResponse
    {
        try {
            // Получаем пассажиров по external_race_id
            $passengers = Passenger::where('external_race_id', $externalRaceId)
                ->with('trip.route.departureStation', 'trip.route.arrivalStation')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $passengers->values(),
                'total_count' => $passengers->count(),
                'external_race_id' => $externalRaceId,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get passengers by race", [
                'external_race_id' => $externalRaceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get passengers: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить пассажиров по рейсам из локальной БД
     * POST /api/passengers/load-by-races
     * Body: { "race_ids": ["race_id_1", "race_id_2", ...] }
     */
    public function loadByRaces(Request $request): JsonResponse
    {
        $request->validate([
            'race_ids' => 'required|array|min:1',
            'race_ids.*' => 'required|string', // ID рейсов из API перевозчика
        ]);

        try {
            $raceIds = array_map('strval', $request->input('race_ids'));

            $trips = Trip::whereIn('external_id', $raceIds)
                ->orWhereIn('id', $raceIds)
                ->with('passengers')
                ->get();

            $passengers = $trips->pluck('passengers')->flatten();
            $validPassengers = $passengers->filter(fn ($passenger) => $passenger->canReceiveNotifications());

            return response()->json([
                'success' => true,
                'message' => 'Passengers loaded from local database successfully',
                'data' => [
                    'total_loaded' => $passengers->count(),
                    'saved_count' => $passengers->count(),
                    'valid_count' => $validPassengers->count(),
                    'race_ids' => $raceIds,
                    'trip_ids' => $trips->pluck('id')->unique()->values(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to load passengers by races", [
                'race_ids' => $request->input('race_ids'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load passengers: ' . $e->getMessage(),
            ], 500);
        }
    }
}


