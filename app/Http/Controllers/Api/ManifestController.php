<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TripManifest;
use App\Models\TripManifestItem;
use App\Models\Passenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class ManifestController extends Controller
{
    /**
     * Получить или создать ведомость рейса
     * GET /api/manifests/{externalRouteId}?dt_depart={iso8601}&external_trip_id={id}
     */
    public function show(Request $request, string $externalRouteId): JsonResponse
    {
        $request->validate([
            'dt_depart' => 'required|date',
            'external_trip_id' => 'nullable|string',
            'provider' => 'nullable|string',
        ]);

        $dtDepart = $request->input('dt_depart');
        $externalTripId = $request->input('external_trip_id');
        $provider = $request->input('provider', 'РФБАС');

        try {
            DB::beginTransaction();

            // Ищем существующую ведомость
            $manifest = TripManifest::byProvider($provider)
                ->byRouteAndDate($externalRouteId, $dtDepart)
                ->first();

            // Если ведомость не существует, создаём её
            if (!$manifest) {
                $manifest = $this->createManifest(
                    $provider,
                    $externalRouteId,
                    $externalTripId,
                    $dtDepart,
                    $request
                );
            }

            DB::commit();

            // Загружаем ведомость с пассажирами
            $manifest->load(['items.passenger', 'items.checkedInBy']);

            return response()->json([
                'success' => true,
                'data' => [
                    'manifest' => $manifest,
                    'items' => $manifest->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'manifest_id' => $item->manifest_id,
                            'passenger_id' => $item->passenger_id,
                            'checked_in' => $item->checked_in,
                            'checked_in_at' => $item->checked_in_at,
                            'checked_in_by' => $item->checked_in_by,
                            'checked_in_by_user' => $item->checkedInBy ? [
                                'id' => $item->checkedInBy->id,
                                'name' => $item->checkedInBy->name,
                            ] : null,
                            'passenger' => $item->passenger ? [
                                'id' => $item->passenger->id,
                                'first_name' => $item->passenger->first_name,
                                'last_name' => $item->passenger->last_name,
                                'middle_name' => $item->passenger->middle_name,
                                'full_name' => $item->passenger->full_name,
                                'seat_number' => $item->passenger->seat_number,
                                'phone' => $item->passenger->phone,
                                'email' => $item->passenger->email,
                                'document_type' => $item->passenger->document_type,
                                'document_series' => $item->passenger->document_series,
                                'document_number' => $item->passenger->document_number,
                                'ticket_price' => $item->passenger->ticket_price,
                                'ticket_total_price' => $item->passenger->ticket_total_price,
                                'passenger_type' => $item->passenger->passenger_type,
                                'ticket_purchased_at' => $item->passenger->ticket_purchased_at,
                            ] : null,
                        ];
                    }),
                    'stats' => [
                        'total' => $manifest->items->count(),
                        'checked_in' => $manifest->getCheckedInCount(),
                        'not_checked_in' => $manifest->getNotCheckedInCount(),
                        'pending' => $manifest->getPendingCount(),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error("Failed to get/create manifest", [
                'external_route_id' => $externalRouteId,
                'dt_depart' => $dtDepart,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении ведомости: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Создать новую ведомость и заполнить пассажирами
     */
    protected function createManifest(
        string $provider,
        string $externalRouteId,
        ?string $externalTripId,
        string $dtDepart,
        Request $request
    ): TripManifest {
        // Создаём ведомость
        $manifest = TripManifest::create([
            'provider' => $provider,
            'external_route_id' => $externalRouteId,
            'external_trip_id' => $externalTripId,
            'dt_depart' => $dtDepart,
            'dt_arrive' => $request->input('dt_arrive'),
            'from_id' => $request->input('from_id'),
            'to_id' => $request->input('to_id'),
            'from_name' => $request->input('from_name'),
            'to_name' => $request->input('to_name'),
            'route_number' => $request->input('route_number'),
            'bus_number' => $request->input('bus_number'),
            'vehicle_model' => $request->input('vehicle_model'),
            'carrier_name' => $request->input('carrier_name'),
            'created_by' => auth()->id(),
            'status' => 'draft',
        ]);

        // Загружаем пассажиров из БД по external_race_id (это external_trip_id)
        if ($externalTripId) {
            $passengers = Passenger::where('external_race_id', $externalTripId)->get();

            // Создаём записи в trip_manifest_items для каждого пассажира
            foreach ($passengers as $passenger) {
                TripManifestItem::create([
                    'manifest_id' => $manifest->id,
                    'passenger_id' => $passenger->id,
                    'checked_in' => null,
                ]);
            }

            Log::info("Created manifest with passengers", [
                'manifest_id' => $manifest->id,
                'external_route_id' => $externalRouteId,
                'passengers_count' => $passengers->count(),
            ]);
        }

        return $manifest;
    }

    /**
     * Отметить явку/неявку пассажира
     * POST /api/manifests/{manifestId}/check-in
     * Body: { "item_id": 123, "checked_in": true }
     */
    public function checkIn(Request $request, int $manifestId): JsonResponse
    {
        $request->validate([
            'item_id' => 'required|exists:trip_manifest_items,id',
            'checked_in' => 'required|boolean',
        ]);

        try {
            $item = TripManifestItem::where('id', $request->input('item_id'))
                ->where('manifest_id', $manifestId)
                ->firstOrFail();

            $checkedIn = $request->input('checked_in');
            
            if ($checkedIn) {
                $item->markCheckedIn(auth()->id());
            } else {
                $item->markNotCheckedIn(auth()->id());
            }

            return response()->json([
                'success' => true,
                'message' => 'Отметка сохранена',
                'data' => [
                    'item_id' => $item->id,
                    'checked_in' => $item->checked_in,
                    'checked_in_at' => $item->checked_in_at,
                    'checked_in_by' => $item->checked_in_by,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to check in passenger", [
                'manifest_id' => $manifestId,
                'item_id' => $request->input('item_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при сохранении отметки: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Экспорт ведомости в PDF
     * GET /api/manifests/{manifestId}/pdf
     */
    public function exportPdf(int $manifestId)
    {
        try {
            $manifest = TripManifest::with(['items.passenger', 'items.checkedInBy', 'creator'])
                ->findOrFail($manifestId);

            // Получаем имена водителей (если есть связь с водителями)
            $driverNames = '—';
            // TODO: Добавить получение водителей если есть связь

            // Генерируем PDF
            $pdf = Pdf::loadView('pdf.manifest', [
                'manifest' => $manifest,
                'items' => $manifest->items,
                'driverNames' => $driverNames,
            ]);

            // Устанавливаем ориентацию и размер бумаги
            $pdf->setPaper('A4', 'landscape');

            // Формируем имя файла
            $filename = sprintf(
                'vedomost_%s_%s.pdf',
                $manifest->route_number ?? 'route',
                $manifest->dt_depart ? \Carbon\Carbon::parse($manifest->dt_depart)->format('Y-m-d') : 'date'
            );

            // Возвращаем PDF для скачивания
            return $pdf->download($filename);

        } catch (\Exception $e) {
            Log::error("Failed to export manifest to PDF", [
                'manifest_id' => $manifestId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при экспорте в PDF: ' . $e->getMessage(),
            ], 500);
        }
    }
}
