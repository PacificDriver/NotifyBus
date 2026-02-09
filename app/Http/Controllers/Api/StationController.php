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
     * Получить список всех активных станций с external_id
     * Возвращает только станции, которые имеют external_id (синхронизированы с API)
     */
    public function index(Request $request): JsonResponse
    {
        $stations = Station::active()
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->where('external_id', '!=', '0')
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

    /**
     * Удалить все станции из базы данных
     * Доступно только администраторам
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Log::info('Starting stations clearing');
            
            $count = Station::count();
            
            if ($count === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'В базе данных нет станций для удаления',
                    'deleted_count' => 0,
                ]);
            }
            
            // Удаляем все станции
            Station::query()->delete();
            
            \Illuminate\Support\Facades\Log::info('Stations cleared successfully', [
                'deleted_count' => $count,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Успешно удалено станций: {$count}",
                'deleted_count' => $count,
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Stations clearing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении станций: ' . $e->getMessage(),
            ], 500);
        }
    }
}


