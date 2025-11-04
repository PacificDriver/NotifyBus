<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use Illuminate\Http\JsonResponse;

class PassengerController extends Controller
{
    /**
     * Получить список пассажиров для конкретного рейса
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
}


