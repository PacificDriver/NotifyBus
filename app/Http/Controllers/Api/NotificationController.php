<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Получить список уведомлений
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::with(['passenger', 'trip', 'notificationTask']);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('channel')) {
            $query->byChannel($request->input('channel'));
        }

        if ($request->has('task_id')) {
            $query->where('notification_task_id', $request->input('task_id'));
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }

    /**
     * Получить информацию об уведомлении
     */
    public function show(int $id): JsonResponse
    {
        $notification = Notification::with([
            'passenger',
            'trip.route.departureStation',
            'trip.route.arrivalStation',
            'notificationTask',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $notification,
        ]);
    }
}


