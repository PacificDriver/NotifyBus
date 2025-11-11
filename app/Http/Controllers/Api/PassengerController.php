<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passenger;
use App\Models\Trip;
use App\Services\ExternalDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PassengerController extends Controller
{
    protected ExternalDatabaseService $externalDbService;

    public function __construct(ExternalDatabaseService $externalDbService)
    {
        $this->externalDbService = $externalDbService;
    }

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
     * Загрузить пассажиров из внешней БД для отмененных рейсов
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
            $raceIds = $request->input('race_ids');
            
            // Загружаем пассажиров из внешней БД по ID рейсов
            $passengersData = $this->externalDbService->getPassengersByRaceIds($raceIds);

            // Сохраняем пассажиров в локальную БД
            // Для этого нужно найти соответствующие trips в локальной БД по external_id
            $savedCount = 0;
            $skippedCount = 0;

            foreach ($passengersData as $passengerData) {
                $raceId = $passengerData['race_id'] ?? null;
                
                if (!$raceId) {
                    $skippedCount++;
                    continue;
                }

                // Находим trip по external_id (ID рейса из API)
                $trip = Trip::where('external_id', $raceId)->first();
                
                if (!$trip) {
                    // Если trip не найден, создаем запись о пассажире без trip_id
                    // или пропускаем (в зависимости от требований)
                    Log::warning("Trip not found for race_id", [
                        'race_id' => $raceId,
                        'passenger' => $passengerData,
                    ]);
                    $skippedCount++;
                    continue;
                }

                // Сохраняем или обновляем пассажира
                Passenger::updateOrCreate(
                    [
                        'trip_id' => $trip->id,
                        'external_booking_id' => $passengerData['external_booking_id'],
                    ],
                    [
                        'first_name' => $passengerData['first_name'] ?? '',
                        'last_name' => $passengerData['last_name'] ?? '',
                        'middle_name' => $passengerData['middle_name'] ?? null,
                        'email' => $passengerData['email'] ?? null,
                        'phone' => $passengerData['phone'] ?? null,
                        'birth_date' => $passengerData['birth_date'] ?? null,
                        'document_type' => $passengerData['document_type'] ?? null,
                        'document_series' => $passengerData['document_series'] ?? null,
                        'document_number' => $passengerData['document_number'] ?? null,
                        'document_issued_at' => $passengerData['document_issued_at'] ?? null,
                        'seat_number' => $passengerData['seat_number'] ?? null,
                        'ticket_price' => $passengerData['ticket_price'] ?? null,
                        'ticket_service_fee' => $passengerData['ticket_service_fee'] ?? null,
                        'ticket_total_price' => $passengerData['ticket_total_price'] ?? null,
                        'ticket_discount' => $passengerData['ticket_discount'] ?? null,
                        'ticket_status' => $passengerData['ticket_status'] ?? 'booked',
                        'passenger_type' => $passengerData['passenger_type'] ?? null,
                        'external_order_id' => $passengerData['external_order_id'] ?? null,
                        'ticket_uid' => $passengerData['ticket_uid'] ?? null,
                        'ticket_number' => $passengerData['ticket_number'] ?? null,
                        'ticket_purchased_at' => $passengerData['ticket_purchased_at'] ?? null,
                        'external_payload' => $passengerData['raw_payload'] ?? null,
                    ]
                );

                $savedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => 'Passengers loaded successfully',
                'data' => [
                    'total_loaded' => count($passengersData),
                    'saved_count' => $savedCount,
                    'skipped_count' => $skippedCount,
                    'race_ids' => $raceIds,
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


