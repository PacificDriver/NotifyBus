<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationTask;
use App\Models\Passenger;
use App\Models\Notification;
use App\Models\MessageTemplate;
use App\Models\Trip;
use App\Jobs\SendNotificationJob;
use App\Services\ExternalDatabaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationTaskController extends Controller
{
    protected ExternalDatabaseService $externalDbService;

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
     * Принимает races_data - массив отмененных рейсов из API перевозчика
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'races_data' => 'required|array|min:1', // Данные отмененных рейсов из API
            'races_data.*.id' => 'required|string', // ID рейса (external_id)
            'template_id' => 'nullable|exists:message_templates,id',
            'custom_message' => 'nullable|string',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $task = NotificationTask::create([
            'title' => $validated['title'],
            'races_data' => $validated['races_data'], // Сохраняем данные рейсов
            'trip_ids' => [], // Пустой массив, будет заполнен после загрузки пассажиров
            'template_id' => $validated['template_id'] ?? null,
            'custom_message' => $validated['custom_message'] ?? null,
            'created_by' => $request->user()->id,
            'total_recipients' => 0, // Будет заполнено после загрузки пассажиров
            'status' => 'draft',
            'scheduled_at' => $validated['scheduled_at'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $task,
            'message' => 'Notification task created successfully. Please load passengers next.',
        ], 201);
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

                // Создаем или находим trip
                // TODO: Может потребоваться создать Route и Station если их нет
                $trip = Trip::firstOrCreate(
                    ['external_id' => $raceId],
                    [
                        'route_id' => 1, // TODO: Нужно определить route_id из данных рейса
                        'trip_number' => $raceData['id'] ?? $raceId,
                        'departure_time' => isset($raceData['dt_depart']) ? date('Y-m-d H:i:s', strtotime($raceData['dt_depart'])) : now(),
                        'arrival_time' => isset($raceData['dt_arrive']) ? date('Y-m-d H:i:s', strtotime($raceData['dt_arrive'])) : now(),
                        'status' => 'cancelled',
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
                        'seat_number' => $passengerData['seat_number'] ?? null,
                        'ticket_price' => $passengerData['ticket_price'] ?? null,
                        'ticket_status' => $passengerData['ticket_status'] ?? 'booked',
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

        DB::beginTransaction();
        try {
            // Получаем всех пассажиров для выбранных рейсов
            $passengers = Passenger::whereIn('trip_id', $task->trip_ids)
                ->with('trip.route.departureStation', 'trip.route.arrivalStation')
                ->get()
                ->filter(fn($p) => $p->canReceiveNotifications());

            $template = $task->template;

            // Создаем уведомления для каждого пассажира
            foreach ($passengers as $passenger) {
                // Определяем текст сообщения
                if ($template) {
                    $variables = MessageTemplate::getVariablesForPassenger($passenger, $passenger->trip);
                    $renderedMessage = $template->render($variables);
                    $subject = $renderedMessage['subject'];
                    $message = $renderedMessage['body'];
                } else {
                    $subject = 'Уведомление о рейсе';
                    $message = $task->custom_message;
                }

                // Email уведомление
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

                    // Добавляем в очередь с задержкой
                    SendNotificationJob::dispatch($notification)
                        ->delay(now()->addSeconds(rand(1, 5)));
                }

                // WhatsApp уведомление
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

                    // Добавляем в очередь с задержкой
                    SendNotificationJob::dispatch($notification)
                        ->delay(now()->addSeconds(rand(1, 5)));
                }
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


