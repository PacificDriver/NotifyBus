<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Services\CarrierApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StationController extends Controller
{
    protected CarrierApiService $carrierApiService;

    public function __construct(CarrierApiService $carrierApiService)
    {
        $this->carrierApiService = $carrierApiService;
    }

    /**
     * Получить список всех активных станций
     */
    public function index(Request $request): JsonResponse
    {
        $stations = Station::active()
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stations,
        ]);
    }

    /**
     * Синхронизировать список станций с API перевозчика
     * Доступно только администраторам
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Log::info('Starting stations synchronization');
            
            $syncedCount = $this->carrierApiService->syncStations();
            
            \Illuminate\Support\Facades\Log::info('Stations synchronization completed', [
                'synced_count' => $syncedCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Синхронизация завершена успешно',
                'synced_count' => $syncedCount,
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Stations synchronization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Определяем более понятное сообщение об ошибке
            $userMessage = $e->getMessage();
            
            if (str_contains($userMessage, 'Не удалось подключиться')) {
                $userMessage = 'Не удалось подключиться к API перевозчика. Проверьте URL и доступность сервера.';
            } elseif (str_contains($userMessage, 'Unauthorized') || str_contains($userMessage, '401')) {
                $userMessage = 'Неверный ключ доступа к API перевозчика (x-access-token).';
            } elseif (str_contains($userMessage, 'Forbidden') || str_contains($userMessage, '403')) {
                $userMessage = 'Доступ к API перевозчика запрещён. Проверьте права доступа ключа.';
            }
            
            return response()->json([
                'success' => false,
                'message' => $userMessage,
            ], 400);
        }
    }
}


