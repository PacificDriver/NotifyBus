<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationTask;
use App\Models\Passenger;
use App\Models\Notification;
use App\Models\MessageTemplate;
use App\Jobs\SendNotificationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationTaskController extends Controller
{
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
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'trip_ids' => 'required|array|min:1',
            'trip_ids.*' => 'exists:trips,id',
            'template_id' => 'nullable|exists:message_templates,id',
            'custom_message' => 'nullable|string',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        // Подсчитываем количество пассажиров
        $totalRecipients = Passenger::whereIn('trip_id', $validated['trip_ids'])
            ->get()
            ->filter(fn($p) => $p->canReceiveNotifications())
            ->count();

        $task = NotificationTask::create([
            'title' => $validated['title'],
            'trip_ids' => $validated['trip_ids'],
            'template_id' => $validated['template_id'] ?? null,
            'custom_message' => $validated['custom_message'] ?? null,
            'created_by' => $request->user()->id,
            'total_recipients' => $totalRecipients,
            'status' => 'draft',
            'scheduled_at' => $validated['scheduled_at'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $task,
            'message' => 'Notification task created successfully',
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


