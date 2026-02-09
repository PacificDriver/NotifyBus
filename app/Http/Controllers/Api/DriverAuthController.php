<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class DriverAuthController extends Controller
{
    /**
     * Авторизация водителя
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $driver = Driver::where('username', $request->username)->first();

        if (!$driver || !Hash::check($request->password, $driver->password)) {
            Log::warning('Driver login failed', [
                'username' => $request->username,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'username' => ['Неверный логин или пароль'],
            ]);
        }

        if (!$driver->is_active) {
            Log::warning('Inactive driver tried to login', [
                'driver_id' => $driver->id,
                'username' => $driver->username,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ваш аккаунт деактивирован. Обратитесь к администратору.',
            ], 403);
        }

        // Удаляем старые токены (опционально)
        $driver->tokens()->delete();

        // Создаем новый токен
        $token = $driver->createToken('driver-token')->plainTextToken;

        Log::info('Driver logged in successfully', [
            'driver_id' => $driver->id,
            'username' => $driver->username,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'driver' => [
                    'id' => $driver->id,
                    'full_name' => $driver->full_name,
                    'username' => $driver->username,
                    'phone' => $driver->phone,
                    'email' => $driver->email,
                ],
                'token' => $token,
            ],
            'message' => 'Авторизация успешна',
        ]);
    }

    /**
     * Выход водителя (удаление токена)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $driver = $request->user();

        // Удаляем текущий токен
        $request->user()->currentAccessToken()->delete();

        Log::info('Driver logged out', [
            'driver_id' => $driver->id,
            'username' => $driver->username,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Вы успешно вышли из системы',
        ]);
    }

    /**
     * Получить данные текущего авторизованного водителя
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $driver = $request->user();

        // Загружаем назначенные рейсы с отношениями
        $trips = $driver->trips()
            ->with([
                'route.departureStation',
                'route.arrivalStation',
            ])
            ->orderBy('departure_time', 'asc')
            ->get();

        // Форматируем рейсы в нужный формат
        $formattedTrips = $trips->map(function ($trip) {
            $route = $trip->route;
            $departureStation = $route->departureStation ?? null;
            $arrivalStation = $route->arrivalStation ?? null;

            return [
                'routeNumber' => $route->route_number ?? '',
                'fromExternalId' => $departureStation->external_id ?? '',
                'toExternalId' => $arrivalStation->external_id ?? '',
                'fromName' => $departureStation->name ?? '',
                'toName' => $arrivalStation->name ?? '',
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $driver->id,
                'full_name' => $driver->full_name,
                'first_name' => $driver->first_name,
                'last_name' => $driver->last_name,
                'middle_name' => $driver->middle_name,
                'username' => $driver->username,
                'phone' => $driver->phone,
                'email' => $driver->email,
                'is_active' => $driver->is_active,
                'trips' => $formattedTrips,
            ],
        ]);
    }
}
