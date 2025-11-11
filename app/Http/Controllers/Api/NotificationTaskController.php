<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Services\ExternalDatabaseService;
use App\Models\MessageTemplate;
use App\Models\Notification;
use App\Models\NotificationTask;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\Station;
use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationTaskController extends Controller
{
    protected ExternalDatabaseService $externalDbService;
    protected ?\Illuminate\Support\Collection $stationsCache = null;

    public function __construct(ExternalDatabaseService $externalDbService)
    {
        $this->externalDbService = $externalDbService;
    }
    /**
     * Получить список задач на рассылку
     */
    public function index(Request $request): JsonResponse
    {
        $tasks = NotificationTask::with(['creator', 'template'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $tasks,
        ]);
    }

    /**
     * Создать новую задачу на рассылку
     * Задача создается без рейсов, рейсы добавляются позже через addRaces
     * Название задачи генерируется автоматически на основе текущей даты и времени
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => 'nullable|exists:message_templates,id',
            'custom_message' => 'nullable|string',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        try {
            // Проверяем, что пользователь авторизован
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Пользователь не авторизован',
                ], 401);
            }

            // Генерируем название задачи автоматически на основе текущей даты и времени
            $title = 'Рассылка уведомлений - ' . now()->format('d.m.Y H:i:s');
            
            Log::info("Creating notification task", [
                'user_id' => $user->id,
                'title' => $title,
            ]);
            
            $task = NotificationTask::create([
                'title' => $title, // Генерируем название явно
                'races_data' => [], // Пустой массив, рейсы добавляются позже
                'trip_ids' => [], // Пустой массив, будет заполнен после загрузки пассажиров
                'template_id' => $validated['template_id'] ?? null,
                'custom_message' => $validated['custom_message'] ?? null,
                'created_by' => $user->id,
                'total_recipients' => 0, // Будет заполнено после загрузки пассажиров
                'status' => 'draft',
                'scheduled_at' => $validated['scheduled_at'] ?? null,
            ]);

            Log::info("Notification task created successfully", [
                'task_id' => $task->id,
                'title' => $task->title,
            ]);

            return response()->json([
                'success' => true,
                'data' => $task,
                'message' => 'Задача создана успешно. Теперь можно добавить отмененные рейсы.',
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации: ' . $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error("Failed to create notification task", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании задачи: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Добавить отмененные рейсы в задачу
     * PUT /api/notification-tasks/{id}/add-races
     */
    public function addRaces(Request $request, int $id): JsonResponse
    {
        $task = NotificationTask::findOrFail($id);

        if ($task->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => 'Нельзя изменить задачу со статусом: ' . $task->status,
            ], 400);
        }

        $validated = $request->validate([
            'races_data' => 'required|array|min:1',
            'races_data.*.id' => 'required|string',
            'races_data.*.active' => 'nullable|boolean',
            'races_data.*.route_tz' => 'nullable|integer',
            'races_data.*.dt_depart' => 'nullable|string',
            'races_data.*.dt_arrive' => 'nullable|string',
        ]);

        // Получаем текущие рейсы
        $currentRaces = $task->races_data ?? [];
        
        // Добавляем новые рейсы (проверяем на дубликаты по ID)
        $existingIds = array_column($currentRaces, 'id');
        $newRaces = [];
        
        foreach ($validated['races_data'] as $race) {
            if (!in_array($race['id'], $existingIds)) {
                $newRaces[] = $race;
            }
        }

        // Объединяем старые и новые рейсы
        $updatedRaces = array_merge($currentRaces, $newRaces);

        $task->update([
            'races_data' => $updatedRaces,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'task' => $task,
                'added_count' => count($newRaces),
                'total_races' => count($updatedRaces),
            ],
            'message' => 'Рейсы успешно добавлены в задачу.',
        ]);
    }

    /**
     * Получить информацию о задаче
     */
    public function show(int $id): JsonResponse
    {
        $task = NotificationTask::with([
            'creator',
            'template',
            'notifications.passenger',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $task,
        ]);
    }

    /**
     * Загрузить пассажиров из внешней БД для задачи
     * POST /api/notification-tasks/{id}/load-passengers
     */
    public function loadPassengers(Request $request, int $id): JsonResponse
    {
        $task = NotificationTask::findOrFail($id);

        if (empty($task->races_data)) {
            return response()->json([
                'success' => false,
                'message' => 'Task has no races_data. Please create task with races_data first.',
            ], 400);
        }

        try {
            // Извлекаем ID рейсов из races_data
            $raceIds = array_column($task->races_data, 'id');
            
            if (empty($raceIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No race IDs found in races_data',
                ], 400);
            }

            // Загружаем пассажиров из внешней БД
            $passengersData = $this->externalDbService->getPassengersByRaceIds($raceIds);

            // Сохраняем пассажиров и создаем/обновляем trips
            $savedCount = 0;
            $tripIds = [];

            foreach ($passengersData as $passengerData) {
                $raceId = $passengerData['race_id'] ?? null;
                
                if (!$raceId) {
                    continue;
                }

                // Находим или создаем trip по external_id
                $raceData = collect($task->races_data)->firstWhere('id', $raceId);
                
                if (!$raceData) {
                    Log::warning("Race data not found for race_id", ['race_id' => $raceId]);
                    continue;
                }

                $route = $this->resolveRoute($raceData);

                if (!$route) {
                    Log::warning('Route could not be resolved for race', [
                        'race_id' => $raceId,
                        'race_data' => $raceData,
                    ]);
                    continue;
                }

                $departureTime = $this->parseRaceDateTime($raceData['dt_depart'] ?? null);
                $arrivalTime = $this->parseRaceDateTime($raceData['dt_arrive'] ?? null);

                if (!$arrivalTime && $departureTime) {
                    $arrivalTime = Carbon::parse($departureTime)->addMinutes(90)->toDateTimeString();
                }

                if (!$departureTime) {
                    $departureTime = now()->toDateTimeString();
                }

                if (!$arrivalTime) {
                    $arrivalTime = Carbon::parse($departureTime)->addMinutes(90)->toDateTimeString();
                }

                $trip = Trip::updateOrCreate(
                    ['external_id' => (string) $raceId],
                    [
                        'route_id' => $route->id,
                        'trip_number' => $raceData['trip_number']
                            ?? $raceData['route_number']
                            ?? $raceData['id']
                            ?? $raceId,
                        'departure_time' => $departureTime,
                        'arrival_time' => $arrivalTime,
                        'status' => 'cancelled',
                        'delay_minutes' => $raceData['delay_minutes'] ?? null,
                    ]
                );

                if (!in_array($trip->id, $tripIds)) {
                    $tripIds[] = $trip->id;
                }

                // Сохраняем пассажира
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

            // Обновляем задачу с trip_ids и количеством получателей
            $validPassengersCount = Passenger::whereIn('trip_id', $tripIds)
                ->get()
                ->filter(fn($p) => $p->canReceiveNotifications())
                ->count();

            $task->update([
                'trip_ids' => $tripIds,
                'total_recipients' => $validPassengersCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Passengers loaded successfully',
                'data' => [
                    'total_loaded' => count($passengersData),
                    'saved_count' => $savedCount,
                    'valid_passengers_count' => $validPassengersCount,
                    'trip_ids' => $tripIds,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to load passengers for task", [
                'task_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load passengers: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получить список пассажиров для задачи
     * GET /api/notification-tasks/{id}/passengers
     */
    public function getPassengers(int $id): JsonResponse
    {
        $task = NotificationTask::findOrFail($id);

        if (empty($task->trip_ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No passengers loaded. Please load passengers first.',
            ], 400);
        }

        $passengers = Passenger::whereIn('trip_id', $task->trip_ids)
            ->with('trip')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $passengers,
        ]);
    }

    /**
     * Запустить отправку уведомлений
     */
    public function send(Request $request, int $id): JsonResponse
    {
        $task = NotificationTask::findOrFail($id);

        if ($task->status !== 'draft' && $task->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Task cannot be sent. Current status: ' . $task->status,
            ], 400);
        }

        if (empty($task->trip_ids)) {
            return response()->json([
                'success' => false,
                'message' => 'No passengers loaded. Please load passengers first.',
            ], 400);
        }

        // Получаем список ID пассажиров для отправки (если указан)
        $passengerIds = $request->input('passenger_ids', []);
        $customMessage = $request->input('custom_message');

        DB::beginTransaction();
        try {
            // Получаем пассажиров для выбранных рейсов
            $passengersQuery = Passenger::whereIn('trip_id', $task->trip_ids)
                ->with('trip.route.departureStation', 'trip.route.arrivalStation');

            // Фильтруем по выбранным ID, если указаны
            if (!empty($passengerIds)) {
                $passengersQuery->whereIn('id', $passengerIds);
            }

            $passengers = $passengersQuery->get()
                ->filter(fn($p) => $p->canReceiveNotifications());

            $template = $task->template;

            $batchSize = max(1, (int) config('notifications.batch_size', 10));
            $delayBetweenBatches = max(0, (int) config('notifications.delay_seconds', 2));

            $batchIndex = 0;
            foreach ($passengers->chunk($batchSize) as $chunk) {
                $baseDelay = now()->addSeconds($batchIndex * $delayBetweenBatches);
                $offset = 0;

                foreach ($chunk as $passenger) {
                    $trip = $passenger->trip;

                    if ($template) {
                        $variables = MessageTemplate::getVariablesForPassenger($passenger, $trip);
                        $renderedMessage = $template->render($variables);
                        $subject = $renderedMessage['subject'];
                        $message = $renderedMessage['body'];
                    } else {
                        $subject = 'Уведомление о рейсе';
                        $message = $customMessage ?? $task->custom_message;
                    }

                    $message = $this->replaceSimpleVariables($message, $trip);
                    $notificationDelay = $baseDelay->copy()->addSeconds($offset);

                    if ($passenger->hasEmail()) {
                        $notification = Notification::create([
                            'notification_task_id' => $task->id,
                            'passenger_id' => $passenger->id,
                            'trip_id' => $passenger->trip_id,
                            'channel' => 'email',
                            'recipient' => $passenger->email,
                            'subject' => $subject,
                            'message' => $message,
                            'status' => 'pending',
                        ]);

                        SendNotificationJob::dispatch($notification)
                            ->delay($notificationDelay);
                    }

                    if ($passenger->hasPhone()) {
                        $notification = Notification::create([
                            'notification_task_id' => $task->id,
                            'passenger_id' => $passenger->id,
                            'trip_id' => $passenger->trip_id,
                            'channel' => 'whatsapp',
                            'recipient' => $passenger->phone,
                            'subject' => null,
                            'message' => $message,
                            'status' => 'pending',
                        ]);

                        SendNotificationJob::dispatch($notification)
                            ->delay($notificationDelay);
                    }

                    $offset++;
                }

                $batchIndex++;
            }

            $task->update([
                'status' => 'pending',
                'total_recipients' => $passengers->count(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Notifications queued successfully',
                'total_recipients' => $passengers->count(),
                'total_notifications' => $task->notifications()->count(),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to queue notifications: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function stationCache(): \Illuminate\Support\Collection
    {
        if ($this->stationsCache === null) {
            $this->stationsCache = Station::all();
        }

        return $this->stationsCache;
    }

    protected function refreshStationCache(Station $station): void
    {
        if ($this->stationsCache !== null) {
            $this->stationsCache->push($station);
        }
    }

    protected function resolveRoute(array $raceData): ?Route
    {
        $fromStation = $this->resolveStation($raceData, 'from');
        $toStation = $this->resolveStation($raceData, 'to');

        if (!$fromStation || !$toStation) {
            return null;
        }

        $routeNumber = $raceData['route_number']
            ?? $raceData['trip_number']
            ?? $raceData['id']
            ?? null;

        $route = Route::firstOrCreate(
            [
                'departure_station_id' => $fromStation->id,
                'arrival_station_id' => $toStation->id,
            ],
            [
                'route_number' => $routeNumber,
                'is_active' => false,
            ]
        );

        if ($routeNumber && $route->route_number !== $routeNumber) {
            $route->update(['route_number' => $routeNumber]);
        }

        return $route;
    }

    protected function resolveStation(array $raceData, string $direction): ?Station
    {
        $direction = strtolower($direction);

        $idKeys = [
            "{$direction}_station_external_id",
            "{$direction}_station_id",
            "{$direction}_external_id",
            "{$direction}_id",
            "{$direction}_station",
        ];

        if ($direction === 'from') {
            $idKeys[] = 'from_id';
            $idKeys[] = 'departure_station_id';
        } else {
            $idKeys[] = 'to_id';
            $idKeys[] = 'arrival_station_id';
        }

        $nameKeys = [
            $direction === 'from' ? 'route_start' : 'route_end',
            "{$direction}_station_name",
            "{$direction}_name",
            "{$direction}_station",
            $direction === 'from' ? 'departure_station_name' : 'arrival_station_name',
        ];

        $name = null;
        foreach ($nameKeys as $key) {
            if (!empty($raceData[$key])) {
                $name = trim((string) $raceData[$key]);
                break;
            }
        }

        foreach ($idKeys as $key) {
            if (!empty($raceData[$key])) {
                $externalId = (string) $raceData[$key];

                $station = Station::where('external_id', $externalId)->first();

                if ($station) {
                    if ($name && !$station->name) {
                        $station->name = $name;
                        $station->save();
                        $this->refreshStationCache($station);
                    }

                    return $station;
                }

                $station = Station::create([
                    'external_id' => $externalId,
                    'name' => $name ?? "Станция {$externalId}",
                    'code' => null,
                    'city' => null,
                    'region' => 'Сахалинская область',
                    'is_active' => true,
                ]);

                $this->refreshStationCache($station);

                return $station;
            }
        }

        if ($name) {
            $station = $this->findStationByName($name);

            if ($station) {
                return $station;
            }

            $station = Station::create([
                'name' => $name,
                'code' => null,
                'city' => null,
                'region' => 'Сахалинская область',
                'is_active' => true,
            ]);

            $this->refreshStationCache($station);

            return $station;
        }

        return null;
    }

    protected function findStationByName(string $name): ?Station
    {
        $normalized = mb_strtolower(trim($name));

        if ($normalized === '') {
            return null;
        }

        $stations = $this->stationCache();

        $exact = $stations->first(function (Station $station) use ($normalized) {
            return mb_strtolower(trim($station->name)) === $normalized;
        });

        if ($exact) {
            return $exact;
        }

        return $stations->first(function (Station $station) use ($normalized) {
            $stationName = mb_strtolower(trim($station->name));

            if ($stationName === '') {
                return false;
            }

            return str_contains($normalized, $stationName) || str_contains($stationName, $normalized);
        });
    }

    protected function parseRaceDateTime(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateTimeString();
        } catch (\Exception $e) {
            Log::warning('Failed to parse race datetime', [
                'value' => $value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Заменить простые переменные {РЕЙС}, {ДАТА}, {ВРЕМЯ} в сообщении
     */
    protected function replaceSimpleVariables(string $message, Trip $trip): string
    {
        // Получаем данные о рейсе
        $tripNumber = $trip->trip_number ?? 'N/A';
        
        // Формат даты: 31.10.25
        $date = $trip->departure_time ? $trip->departure_time->format('d.m.y') : 'N/A';
        
        // Формат времени: 12:00
        $time = $trip->departure_time ? $trip->departure_time->format('H:i') : 'N/A';
        
        // Формируем строку рейса: "№ 510 Южно-Сахалинск-Макаров"
        $routeInfo = '';
        if ($trip->route) {
            $from = $trip->route->departureStation->name ?? 'N/A';
            $to = $trip->route->arrivalStation->name ?? 'N/A';
            $routeInfo = "№ {$tripNumber} {$from}-{$to}";
        } else {
            $routeInfo = "№ {$tripNumber}";
        }

        // Замена переменных
        $replacements = [
            '{РЕЙС}' => $routeInfo,
            '{ДАТА}' => $date,
            '{ВРЕМЯ}' => $time,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Получить статус отправки задачи
     */
    public function getStatus(int $id): JsonResponse
    {
        $task = NotificationTask::with(['notifications' => function ($query) {
            $query->select('notification_task_id', 'status', 'channel', DB::raw('count(*) as count'))
                  ->groupBy('notification_task_id', 'status', 'channel');
        }])->findOrFail($id);

        $stats = [
            'total' => $task->total_recipients,
            'sent' => $task->sent_count,
            'failed' => $task->failed_count,
            'pending' => $task->notifications()->where('status', 'pending')->count(),
            'queued' => $task->notifications()->where('status', 'queued')->count(),
            'success_rate' => $task->getSuccessRate(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'task' => $task,
                'stats' => $stats,
            ],
        ]);
    }
}


