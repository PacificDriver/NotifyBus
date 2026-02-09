<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Station;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DriverController extends Controller
{
    /**
     * Получить список водителей
     */
    public function index(Request $request): JsonResponse
    {
        $query = Driver::query();

        // Фильтрация по статусу активности
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Поиск по имени или username
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('middle_name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $drivers = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $drivers,
        ]);
    }

    /**
     * Создать нового водителя
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:drivers,username',
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
        ]);

        // Если пароль не указан, генерируем случайный
        if (empty($validated['password'])) {
            $validated['password'] = Str::random(12);
            $generatedPassword = $validated['password'];
        } else {
            $generatedPassword = null;
        }

        // Хешируем пароль
        $validated['password'] = Hash::make($validated['password']);

        $driver = Driver::create($validated);

        Log::info('Driver created', [
            'driver_id' => $driver->id,
            'username' => $driver->username,
            'created_by' => $request->user()->id,
        ]);

        $response = [
            'success' => true,
            'data' => $driver,
            'message' => 'Водитель успешно создан',
        ];

        // Если пароль был сгенерирован, возвращаем его (только при создании!)
        if ($generatedPassword) {
            $response['generated_password'] = $generatedPassword;
            $response['message'] .= '. Сохраните сгенерированный пароль!';
        }

        return response()->json($response, 201);
    }

    /**
     * Получить информацию о водителе
     */
    public function show(int $id): JsonResponse
    {
        $driver = Driver::with(['trips.route.departureStation', 'trips.route.arrivalStation'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $driver,
        ]);
    }

    /**
     * Обновить данные водителя
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $driver = Driver::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'username' => 'sometimes|required|string|max:255|unique:drivers,username,' . $id,
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'is_active' => 'boolean',
        ]);

        // Если указан новый пароль, хешируем его
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $driver->update($validated);

        Log::info('Driver updated', [
            'driver_id' => $driver->id,
            'username' => $driver->username,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $driver,
            'message' => 'Данные водителя обновлены',
        ]);
    }

    /**
     * Удалить водителя
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $driver = Driver::findOrFail($id);

        Log::info('Driver deleted', [
            'driver_id' => $driver->id,
            'username' => $driver->username,
            'deleted_by' => $request->user()->id,
        ]);

        $driver->delete();

        return response()->json([
            'success' => true,
            'message' => 'Водитель удален',
        ]);
    }

    /**
     * Назначить рейс водителю (создает Trip если не существует)
     * POST /api/admin/drivers/{id}/assign-race
     */
    public function assignRace(Request $request, int $id): JsonResponse
    {
        $driver = Driver::findOrFail($id);

        $validated = $request->validate([
            'race_id' => 'required|string',
            'from_station_id' => 'required',
            'to_station_id' => 'required',
            'departure_time' => 'required',
            'arrival_time' => 'nullable',
            'route_number' => 'nullable|string',
            'from_station_name' => 'nullable|string',
            'to_station_name' => 'nullable|string',
        ]);

        try {
            // Находим станции по external_id или внутреннему ID
            $fromStation = Station::where('external_id', (string)$validated['from_station_id'])
                ->orWhere('id', $validated['from_station_id'])
                ->first();
            
            $toStation = Station::where('external_id', (string)$validated['to_station_id'])
                ->orWhere('id', $validated['to_station_id'])
                ->first();
            
            if (!$fromStation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Станция отправления не найдена (ID: ' . $validated['from_station_id'] . ')',
                ], 400);
            }
            
            if (!$toStation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Станция прибытия не найдена (ID: ' . $validated['to_station_id'] . ')',
                ], 400);
            }

            // Получаем или создаем маршрут
            $route = \App\Models\Route::firstOrCreate([
                'departure_station_id' => $fromStation->id,
                'arrival_station_id' => $toStation->id,
            ], [
                'route_number' => $validated['route_number'] ?? 'unknown',
                'name' => ($validated['from_station_name'] ?? '') . ' - ' . ($validated['to_station_name'] ?? ''),
                'is_active' => true,
            ]);

            // Получаем или создаем Trip
            $trip = Trip::firstOrCreate([
                'external_id' => $validated['race_id'],
            ], [
                'route_id' => $route->id,
                'trip_number' => $validated['route_number'] ?? $validated['race_id'],
                'departure_time' => $validated['departure_time'],
                'arrival_time' => $validated['arrival_time'] ?? \Carbon\Carbon::parse($validated['departure_time'])->addHour(),
                'status' => 'scheduled',
            ]);

            // Проверяем, не назначен ли уже этот рейс
            if ($driver->hasTrip($trip)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Этот рейс уже назначен данному водителю',
                ], 400);
            }

            // Назначаем рейс водителю
            $driver->assignTrip($trip, $request->user());

            Log::info('Race assigned to driver', [
                'driver_id' => $driver->id,
                'trip_id' => $trip->id,
                'race_id' => $validated['race_id'],
                'assigned_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Рейс успешно назначен водителю',
                'data' => [
                    'trip_id' => $trip->id,
                    'race_id' => $validated['race_id'],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to assign race to driver', [
                'driver_id' => $id,
                'race_id' => $validated['race_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при назначении рейса: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Назначить рейс водителю (старый метод, требует существующий trip_id)
     */
    public function assignTrip(Request $request, int $id): JsonResponse
    {
        $driver = Driver::findOrFail($id);

        $validated = $request->validate([
            'trip_id' => 'required|exists:trips,id',
        ]);

        $trip = Trip::findOrFail($validated['trip_id']);

        // Проверяем, не назначен ли уже этот рейс
        if ($driver->hasTrip($trip)) {
            return response()->json([
                'success' => false,
                'message' => 'Этот рейс уже назначен данному водителю',
            ], 400);
        }

        $driver->assignTrip($trip, $request->user());

        Log::info('Trip assigned to driver', [
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'assigned_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Рейс успешно назначен водителю',
        ]);
    }

    /**
     * Снять рейс с водителя
     */
    public function unassignTrip(Request $request, int $id, int $tripId): JsonResponse
    {
        $driver = Driver::findOrFail($id);
        $trip = Trip::findOrFail($tripId);

        if (!$driver->hasTrip($trip)) {
            return response()->json([
                'success' => false,
                'message' => 'Этот рейс не назначен данному водителю',
            ], 400);
        }

        $driver->unassignTrip($trip);

        Log::info('Trip unassigned from driver', [
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'unassigned_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Рейс снят с водителя',
        ]);
    }

    /**
     * Получить рейсы конкретного водителя
     */
    public function trips(int $id): JsonResponse
    {
        $driver = Driver::findOrFail($id);

        $trips = $driver->trips()
            ->with([
                'route.departureStation',
                'route.arrivalStation',
            ])
            ->orderBy('departure_time', 'asc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $trips,
        ]);
    }
}
