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
            $syncedCount = $this->carrierApiService->syncStations();

            return response()->json([
                'success' => true,
                'message' => 'Stations synchronization completed',
                'synced_count' => $syncedCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync stations: ' . $e->getMessage(),
            ], 500);
        }
    }
}


