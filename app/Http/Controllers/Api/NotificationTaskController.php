<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\MessageTemplate;
use App\Models\Notification;
use App\Models\NotificationTask;
use App\Models\Passenger;
use App\Models\Route;
use App\Models\Station;
use App\Models\Trip;
use App\Services\StartportPassengerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationTaskController extends Controller
{
    protected ?\Illuminate\Support\Collection $stationsCache = null;

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
            // Используем часовой пояс Asia/Sakhalin (UTC+11)
            $title = 'Рассылка уведомлений - ' . now('Asia/Sakhalin')->format('d.m.Y H:i:s');
            
            Log::info("Creating notification task", [
                'user_id' => $user->id,
                'title' => $title,
            ]);
            
            $task = new NotificationTask();
            $task->title = $title;
            $task->races_data = [];
            $task->trip_ids = [];
            $task->template_id = $validated['template_id'] ?? null;
            $task->custom_message = $validated['custom_message'] ?? null;
            $task->created_by = $user->id;
            $task->total_recipients = 0;
            $task->status = 'draft';
            $task->scheduled_at = $validated['scheduled_at'] ?? null;
            $task->save();

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
            'races_data.*.status' => 'nullable|in:scheduled,cancelled,delayed,completed',
            'races_data.*.route_tz' => 'nullable|integer',
            'races_data.*.dt_depart' => 'nullable|string',
            'races_data.*.dt_arrive' => 'nullable|string',
            'races_data.*.from_station_id' => 'nullable|integer',
            'races_data.*.to_station_id' => 'nullable|integer',
            'races_data.*.from_station_name' => 'nullable|string',
            'races_data.*.to_station_name' => 'nullable|string',
            'races_data.*.route_number' => 'nullable|string',
            'races_data.*.trip_number' => 'nullable|string',
            'races_data.*.route' => 'nullable|string', // Поле из API, будет нормализовано в route_number
            'races_data.*.route_start' => 'nullable|string', // Поле из API
            'races_data.*.route_end' => 'nullable|string', // Поле из API
        ]);
        
        // Нормализуем данные: route -> route_number, route_start/route_end -> from_station_name/to_station_name
        foreach ($validated['races_data'] as &$race) {
            // Если есть route, но нет route_number, копируем
            if (isset($race['route']) && !isset($race['route_number'])) {
                $race['route_number'] = $race['route'];
            }
            
            // Если есть route_start, но нет from_station_name, копируем
            if (isset($race['route_start']) && !isset($race['from_station_name'])) {
                $race['from_station_name'] = $race['route_start'];
            }
            
            // Если есть route_end, но нет to_station_name, копируем
            if (isset($race['route_end']) && !isset($race['to_station_name'])) {
                $race['to_station_name'] = $race['route_end'];
            }
        }
        unset($race); // Важно: сбрасываем ссылку

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
     * Загрузить пассажиров для задачи из локальной БД
     * Если Trip не существуют, создает их автоматически из races_data
     * POST /api/notification-tasks/{id}/load-passengers
     */
    public function loadPassengers(Request $request, int $id): JsonResponse
    {
        Log::info("=== loadPassengers METHOD CALLED ===", [
            'task_id' => $id,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'user_id' => $request->user()?->id,
        ]);

        $task = NotificationTask::findOrFail($id);

        if (empty($task->races_data)) {
            return response()->json([
                'success' => false,
                'message' => 'Task has no races_data. Please create task with races_data first.',
            ], 400);
        }

        // Извлекаем ID рейсов из races_data
        $raceIds = array_map('strval', array_column($task->races_data, 'id'));

        if (empty($raceIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No race IDs found in races_data',
            ], 400);
        }

        Log::info("Loading passengers for task", [
            'task_id' => $id,
            'race_ids' => $raceIds,
            'races_data_sample' => count($task->races_data) > 0 ? $task->races_data[0] : null,
        ]);

        try {
            // Преобразуем race_ids в числа для поиска по id
            $raceIdsAsNumbers = array_map(function($id) {
                return is_numeric($id) ? (int)$id : null;
            }, $raceIds);
            $raceIdsAsNumbers = array_filter($raceIdsAsNumbers); // Убираем null

            Log::info("Searching for trips", [
                'task_id' => $id,
                'race_ids_strings' => $raceIds,
                'race_ids_numbers' => $raceIdsAsNumbers,
            ]);

            // Ищем существующие trips по external_id (строка) или по id (число)
            $trips = Trip::where(function($query) use ($raceIds, $raceIdsAsNumbers) {
                $query->whereIn('external_id', $raceIds);
                if (!empty($raceIdsAsNumbers)) {
                    $query->orWhereIn('id', $raceIdsAsNumbers);
                }
            })->get();
            
            Log::info("Found existing trips", [
                'task_id' => $id,
                'trips_count' => $trips->count(),
                'trip_ids' => $trips->pluck('id')->toArray(),
                'trip_external_ids' => $trips->pluck('external_id')->toArray(),
            ]);

            // Если trips не найдены, создаем их из races_data
            if ($trips->isEmpty()) {
                Log::info("No trips found in database, creating from races_data", [
                    'task_id' => $id,
                    'race_ids' => $raceIds,
                ]);

                $createdTrips = [];
                foreach ($task->races_data as $raceData) {
                    $trip = $this->createTripFromRaceData($raceData);
                    if ($trip) {
                        $createdTrips[] = $trip;
                    }
                }

                $trips = collect($createdTrips);

                if ($trips->isEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to create trips from races_data. Please check race data format.',
                        'data' => [
                            'total_loaded' => 0,
                            'saved_count' => 0,
                            'valid_passengers_count' => 0,
                            'trip_ids' => [],
                        ],
                    ], 400);
                }

                Log::info("Successfully created trips from races_data", [
                    'task_id' => $id,
                    'created_count' => $trips->count(),
                ]);
            }

            $tripIds = $trips->pluck('id')->unique()->values()->all();

            Log::info("Searching for passengers", [
                'task_id' => $id,
                'trip_ids' => $tripIds,
            ]);

            // Загружаем пассажиров из Startport API (если настроен)
            // Пассажиры из pb_order_item уже должны быть загружены фоновым процессом импорта
            $startportService = app(StartportPassengerService::class);
            $startportPassengersCount = 0;

            if ($startportService->isEnabled()) {
                Log::info("Startport API is enabled, loading passengers from Startport", [
                    'task_id' => $id,
                    'race_ids' => $raceIds,
                ]);

                foreach ($trips as $trip) {
                    try {
                        $startportPassengers = $startportService->getPassengersByRoute($trip->external_id);
                        
                        Log::info("Loaded passengers from Startport for trip", [
                            'trip_id' => $trip->id,
                            'external_id' => $trip->external_id,
                            'passengers_count' => count($startportPassengers),
                        ]);

                        // Сохраняем пассажиров в базу данных
                        foreach ($startportPassengers as $passengerData) {
                            try {
                                $normalizedData = $startportService->normalizePassengerData(
                                    $passengerData,
                                    $trip->id,
                                    $trip->external_id
                                );

                                // Используем documentId + ticketId как уникальный идентификатор
                                $uniqueAttributes = [
                                    'external_race_id' => $normalizedData['external_race_id'],
                                    'ticket_uid' => $normalizedData['ticket_uid'],
                                ];

                                // Фильтруем null значения для обновления
                                $updateData = array_filter($normalizedData, fn($value) => $value !== null);
                                $updateData['trip_id'] = $trip->id; // Всегда обновляем trip_id

                                $passenger = Passenger::updateOrCreate($uniqueAttributes, $updateData);
                                $startportPassengersCount++;

                                Log::debug("Saved passenger from Startport", [
                                    'passenger_id' => $passenger->id,
                                    'trip_id' => $trip->id,
                                    'document_id' => $passengerData['documentId'] ?? null,
                                ]);

                            } catch (\Exception $e) {
                                Log::error("Failed to save passenger from Startport", [
                                    'trip_id' => $trip->id,
                                    'passenger_data' => $passengerData,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                    } catch (\Exception $e) {
                        Log::warning("Failed to load passengers from Startport for trip", [
                            'trip_id' => $trip->id,
                            'external_id' => $trip->external_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                Log::info("Loaded passengers from Startport", [
                    'task_id' => $id,
                    'passengers_count' => $startportPassengersCount,
                ]);
            } else {
                Log::info("Startport API is not enabled, skipping", [
                    'task_id' => $id,
                ]);
            }

            // Получаем всех пассажиров из базы данных
            // (уже загруженных из pb_order_item фоновым процессом + только что загруженных из Startport)
            $passengers = Passenger::whereIn('trip_id', $tripIds)->get();

            Log::info("Found passengers", [
                'task_id' => $id,
                'passengers_count' => $passengers->count(),
                'from_startport' => $startportPassengersCount,
                'passenger_ids' => $passengers->pluck('id')->take(10)->toArray(), // Первые 10 для примера
            ]);

            if ($passengers->isEmpty()) {
                $task->update([
                    'trip_ids' => $tripIds,
                    'total_recipients' => 0,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Trips created successfully, but no passengers found in database for provided race IDs',
                    'data' => [
                        'total_loaded' => 0,
                        'saved_count' => 0,
                        'valid_passengers_count' => 0,
                        'trip_ids' => $tripIds,
                        'startport_loaded' => $startportPassengersCount,
                    ],
                ]);
            }

            $validPassengersCount = $passengers
                ->filter(fn ($passenger) => $passenger->canReceiveNotifications())
                ->count();

            $task->update([
                'trip_ids' => $tripIds,
                'total_recipients' => $validPassengersCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Passengers loaded successfully',
                'data' => [
                    'total_loaded' => $passengers->count(),
                    'saved_count' => $passengers->count(),
                    'valid_passengers_count' => $validPassengersCount,
                    'trip_ids' => $tripIds,
                    'startport_loaded' => $startportPassengersCount,
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
     * Создать Trip из данных рейса
     */
    protected function createTripFromRaceData(array $raceData): ?Trip
    {
        try {
            Log::info("Creating trip from race data", [
                'race_id' => $raceData['id'] ?? 'unknown',
                'race_data' => $raceData,
            ]);

            // Получаем или создаем маршрут
            $route = $this->resolveRoute($raceData);
            if (!$route) {
                Log::warning("Failed to resolve route for race", [
                    'race_id' => $raceData['id'] ?? 'unknown',
                    'race_data' => $raceData,
                ]);
                return null;
            }

            // Парсим даты
            $departureTime = $this->parseRaceDateTime($raceData['dt_depart'] ?? null);
            $arrivalTime = $this->parseRaceDateTime($raceData['dt_arrive'] ?? null);

            // Если нет времени отправления, используем текущую дату
            // Используем часовой пояс Asia/Sakhalin (UTC+11)
            if (!$departureTime) {
                $departureTime = now('Asia/Sakhalin')->toDateTimeString();
            }

            // Если нет времени прибытия, используем время отправления + 1 час
            if (!$arrivalTime) {
                $arrivalTime = Carbon::parse($departureTime)->addHours(1)->toDateTimeString();
            }

            // Проверяем, существует ли уже Trip с таким external_id
            $existingTrip = Trip::where('external_id', $raceData['id'])->first();
            if ($existingTrip) {
                Log::info("Trip already exists, returning existing trip", [
                    'trip_id' => $existingTrip->id,
                    'external_id' => $raceData['id'],
                ]);
                return $existingTrip;
            }

            // Создаем Trip
            // Приоритет статуса: 1) status из races_data (установлен оператором), 2) active из API
            $status = 'scheduled'; // По умолчанию
            if (isset($raceData['status'])) {
                // Если оператор установил статус вручную
                $status = $raceData['status'];
            } elseif (isset($raceData['active'])) {
                // Если статус не установлен, используем active из API
                $status = $raceData['active'] ? 'scheduled' : 'cancelled';
            }
            
            $trip = Trip::create([
                'route_id' => $route->id,
                'trip_number' => $raceData['trip_number'] ?? $raceData['route_number'] ?? $raceData['id'],
                'external_id' => $raceData['id'],
                'departure_time' => $departureTime,
                'arrival_time' => $arrivalTime,
                'status' => $status,
                'total_seats' => null,
                'available_seats' => null,
            ]);

            Log::info("Created trip from race data", [
                'trip_id' => $trip->id,
                'external_id' => $trip->external_id,
                'route_id' => $route->id,
            ]);

            return $trip;
        } catch (\Exception $e) {
            Log::error("Failed to create trip from race data", [
                'race_id' => $raceData['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
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

            // Создаем маппинг race_id -> race_data для использования правильных данных
            // Важно: ключи должны быть строками, так как id может быть строкой или числом
            $racesDataMap = [];
            foreach ($task->races_data ?? [] as $raceData) {
                if (isset($raceData['id'])) {
                    // Приводим к строке для надежного сравнения
                    $raceId = (string)$raceData['id'];
                    $racesDataMap[$raceId] = $raceData;
                }
            }
            
            Log::info("Races data map created", [
                'task_id' => $task->id,
                'races_count' => count($racesDataMap),
                'race_ids' => array_keys($racesDataMap),
                'sample_race_data' => !empty($racesDataMap) ? reset($racesDataMap) : null,
            ]);

            $template = $task->template;

            $batchSize = max(1, (int) config('notifications.batch_size', 10));
            $delayBetweenBatches = max(0, (int) config('notifications.delay_seconds', 2));

            $batchIndex = 0;
            foreach ($passengers->chunk($batchSize) as $chunk) {
                $baseDelay = now('Asia/Sakhalin')->addSeconds($batchIndex * $delayBetweenBatches);
                $offset = 0;

                foreach ($chunk as $passenger) {
                    $trip = $passenger->trip;
                    
                    // Получаем данные из races_data для этого пассажира
                    // Пробуем найти по external_race_id пассажира или по external_id Trip
                    $raceData = null;
                    $raceId = null;
                    
                    // Сначала пробуем по external_race_id пассажира
                    if ($passenger->external_race_id) {
                        $raceId = (string)$passenger->external_race_id;
                        if (isset($racesDataMap[$raceId])) {
                            $raceData = $racesDataMap[$raceId];
                        }
                    }
                    
                    // Если не нашли, пробуем по external_id Trip
                    if (!$raceData && $trip->external_id) {
                        $raceId = (string)$trip->external_id;
                        if (isset($racesDataMap[$raceId])) {
                            $raceData = $racesDataMap[$raceId];
                        }
                    }
                    
                    // Логирование для отладки
                    Log::debug("Preparing notification", [
                        'passenger_id' => $passenger->id,
                        'passenger_external_race_id' => $passenger->external_race_id,
                        'trip_id' => $trip->id,
                        'trip_external_id' => $trip->external_id,
                        'trip_trip_number' => $trip->trip_number,
                        'race_id_used' => $raceId,
                        'race_data_found' => $raceData !== null,
                        'race_data_trip_number' => $raceData['trip_number'] ?? null,
                        'races_data_map_keys' => array_keys($racesDataMap),
                    ]);

                    if ($template) {
                        // Передаем raceData для использования правильного trip_number
                        $variables = MessageTemplate::getVariablesForPassenger($passenger, $trip, $raceData);
                        $renderedMessage = $template->render($variables);
                        $subject = $renderedMessage['subject'];
                        $message = $renderedMessage['body'];
                    } else {
                        $subject = 'Уведомление о рейсе';
                        $message = $customMessage ?? $task->custom_message;
                        
                        // Заменяем переменные шаблона даже в custom_message
                        $variables = MessageTemplate::getVariablesForPassenger($passenger, $trip, $raceData);
                        foreach ($variables as $key => $value) {
                            $placeholder = "{{" . $key . "}}";
                            $subject = str_replace($placeholder, (string)$value, $subject);
                            $message = str_replace($placeholder, (string)$value, $message);
                        }
                    }

                    // Используем trip_number из races_data для replaceSimpleVariables
                    $tripNumberForReplace = null;
                    if ($raceData) {
                        $tripNumberForReplace = $raceData['trip_number'] ?? $raceData['route_number'] ?? $raceData['id'] ?? null;
                    }
                    $message = $this->replaceSimpleVariables($message, $trip, $tripNumberForReplace);
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
                            'metadata' => [
                                'email_group' => $passenger->getEmailGroup(),
                            ],
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
        // Если есть прямые ссылки на станции из локальной БД, используем их
        if (!empty($raceData['from_station_id']) && !empty($raceData['to_station_id'])) {
            $fromStation = Station::find($raceData['from_station_id']);
            $toStation = Station::find($raceData['to_station_id']);
            
            if ($fromStation && $toStation) {
                Log::info("Using direct station IDs from race data", [
                    'from_station_id' => $fromStation->id,
                    'to_station_id' => $toStation->id,
                ]);
            } else {
                Log::warning("Station IDs provided but stations not found", [
                    'from_station_id' => $raceData['from_station_id'],
                    'to_station_id' => $raceData['to_station_id'],
                ]);
                $fromStation = null;
                $toStation = null;
            }
        } else {
            $fromStation = $this->resolveStation($raceData, 'from');
            $toStation = $this->resolveStation($raceData, 'to');
        }

        if (!$fromStation || !$toStation) {
            Log::error("Could not resolve stations for route", [
                'race_data' => $raceData,
            ]);
            return null;
        }

        // Приоритет: route_number (нормализованное) > route (из API) > trip_number > id
        $routeNumber = $raceData['route_number']
            ?? $raceData['route']  // Поле из API, если еще не нормализовано
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

        Log::info("Route resolved successfully", [
            'route_id' => $route->id,
            'from_station' => $fromStation->name,
            'to_station' => $toStation->name,
        ]);

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
            // ВАЖНО: Данные из API перевозчика приходят в UTC+3 (московское время)
            // Парсим дату как Europe/Moscow (UTC+3) и конвертируем в UTC для сохранения в БД
            // Laravel автоматически конвертирует в Asia/Sakhalin при чтении благодаря кастингу
            $date = Carbon::parse($value, 'Europe/Moscow')->setTimezone('UTC');
            
            Log::debug('Parsed race datetime', [
                'original' => $value,
                'parsed_moscow' => Carbon::parse($value, 'Europe/Moscow')->format('Y-m-d H:i:s T'),
                'converted_utc' => $date->format('Y-m-d H:i:s T'),
                'will_display_as_sakhalin' => $date->copy()->setTimezone('Asia/Sakhalin')->format('Y-m-d H:i:s T'),
            ]);
            
            return $date->toDateTimeString();
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
     * @param string|null $tripNumber Номер рейса из races_data (если передан, используется вместо trip->trip_number)
     */
    protected function replaceSimpleVariables(string $message, Trip $trip, ?string $tripNumber = null): string
    {
        // Получаем данные о рейсе
        $tripNumber = $tripNumber ?? $trip->trip_number ?? 'N/A';
        
        // Жестко устанавливаем часовой пояс Asia/Sakhalin (UTC+11)
        $timezone = 'Asia/Sakhalin';
        
        // Форматируем время с учетом часового пояса
        // ВАЖНО: Время в БД может быть в UTC или уже в нужном часовом поясе
        // Проверяем текущий часовой пояс объекта и конвертируем только если нужно
        $departureTime = null;
        if ($trip->departure_time) {
            if ($trip->departure_time instanceof \Carbon\Carbon) {
                // Получаем текущий часовой пояс объекта
                $currentTimezone = $trip->departure_time->timezone->getName();
                
                // Если часовой пояс уже правильный, просто используем копию
                if ($currentTimezone === $timezone) {
                    $departureTime = $trip->departure_time->copy();
                } elseif ($currentTimezone === 'UTC') {
                    // Если объект в UTC, конвертируем в нужный часовой пояс
                    $departureTime = $trip->departure_time->copy()->setTimezone($timezone);
                } else {
                    // Если объект в другом часовом поясе, конвертируем через UTC
                    $departureTime = $trip->departure_time->copy()->setTimezone('UTC')->setTimezone($timezone);
                }
            } else {
                // Если это строка, парсим как UTC и конвертируем в нужный часовой пояс
                $departureTime = \Carbon\Carbon::parse($trip->departure_time, 'UTC')->setTimezone($timezone);
            }
        }
        
        // Формат даты: 31.10.25
        $date = $departureTime ? $departureTime->format('d.m.y') : 'N/A';
        
        // Формат времени: 12:00
        $time = $departureTime ? $departureTime->format('H:i') : 'N/A';
        
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