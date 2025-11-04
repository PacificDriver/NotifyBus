<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Контроллер для обработки webhook'ов от Wappi.pro
 * 
 * Документация: https://wappi.pro/api-documentation
 */
class WebhookController extends Controller
{
    /**
     * Обработка webhook'ов от Wappi.pro
     * 
     * Поддерживаемые типы webhook'ов:
     * - delivery_status - статус доставки сообщения
     * - authorization_status - статус профиля
     * - incoming_message - входящее сообщение
     * - outgoing_message_api - исходящее сообщение через API
     */
    public function handle(Request $request)
    {
        try {
            $data = $request->all();
            
            Log::info("Wappi webhook received", [
                'data' => $data,
            ]);

            // Wappi отправляет данные в разных форматах
            // Проверяем формат с messages
            if (isset($data['messages'])) {
                $messages = is_array($data['messages']) ? $data['messages'] : [$data['messages']];
            } else {
                // Если формат другой, пробуем обработать как одно сообщение
                $messages = [$data];
            }

            foreach ($messages as $message) {
                $this->processWebhookMessage($message);
            }

            // Всегда возвращаем 200 OK для Wappi
            return response()->json(['success' => true], 200);

        } catch (\Exception $e) {
            Log::error("Wappi webhook processing error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            // Все равно возвращаем 200, чтобы Wappi не повторял запросы
            return response()->json(['success' => false, 'error' => $e->getMessage()], 200);
        }
    }

    /**
     * Обработать одно сообщение из webhook
     */
    protected function processWebhookMessage(array $message): void
    {
        $whType = $message['wh_type'] ?? null;

        switch ($whType) {
            case 'delivery_status':
                $this->handleDeliveryStatus($message);
                break;

            case 'authorization_status':
                $this->handleAuthorizationStatus($message);
                break;

            case 'incoming_message':
                $this->handleIncomingMessage($message);
                break;

            case 'outgoing_message_api':
                $this->handleOutgoingMessageApi($message);
                break;

            case 'outgoing_message_phone':
                $this->handleOutgoingMessagePhone($message);
                break;

            default:
                Log::warning("Unknown webhook type", [
                    'wh_type' => $whType,
                    'message' => $message,
                ]);
        }
    }

    /**
     * Обработать статус доставки сообщения
     * 
     * Статусы: pending, delivered, read, undelivered, temporary ban, error
     */
    protected function handleDeliveryStatus(array $data): void
    {
        $messageId = $data['id'] ?? null;
        $status = $data['status'] ?? null;
        $taskId = $data['task_id'] ?? null;

        if (empty($messageId) && empty($taskId)) {
            Log::warning("Delivery status webhook without message_id or task_id", ['data' => $data]);
            return;
        }

        // Ищем уведомление по external_message_id или external_task_id
        $notification = null;
        
        if ($messageId) {
            $notification = Notification::where('external_message_id', $messageId)
                ->where('channel', 'whatsapp')
                ->first();
        }
        
        if (!$notification && $taskId) {
            $notification = Notification::where('external_task_id', $taskId)
                ->where('channel', 'whatsapp')
                ->first();
        }

        if (!$notification) {
            Log::warning("Notification not found for delivery status", [
                'message_id' => $messageId,
                'task_id' => $taskId,
                'status' => $status,
            ]);
            return;
        }

        // Обновляем статус уведомления
        switch ($status) {
            case 'delivered':
                $notification->markAsDelivered();
                Log::info("Notification marked as delivered", [
                    'notification_id' => $notification->id,
                    'external_message_id' => $messageId,
                ]);
                break;

            case 'read':
                // Обновляем статус до delivered (read означает, что сообщение прочитано)
                $notification->markAsDelivered();
                $notification->update([
                    'metadata' => array_merge($notification->metadata ?? [], [
                        'read_at' => now()->toIso8601String(),
                    ]),
                ]);
                Log::info("Notification marked as read", [
                    'notification_id' => $notification->id,
                    'external_message_id' => $messageId,
                ]);
                break;

            case 'undelivered':
            case 'error':
            case 'temporary ban':
                $errorMessage = "WhatsApp delivery failed: {$status}";
                $notification->markAsFailed($errorMessage);
                Log::warning("Notification delivery failed", [
                    'notification_id' => $notification->id,
                    'external_message_id' => $messageId,
                    'status' => $status,
                ]);
                break;

            case 'pending':
                // Сообщение еще в очереди, ничего не делаем
                Log::debug("Notification still pending", [
                    'notification_id' => $notification->id,
                    'external_message_id' => $messageId,
                ]);
                break;

            default:
                Log::warning("Unknown delivery status", [
                    'notification_id' => $notification->id,
                    'status' => $status,
                ]);
        }
    }

    /**
     * Обработать статус авторизации профиля
     * 
     * Статусы: online, offline
     */
    protected function handleAuthorizationStatus(array $data): void
    {
        $profileId = $data['profile_id'] ?? null;
        $status = $data['status'] ?? null;
        $reason = $data['reason'] ?? null;

        Log::info("WhatsApp profile authorization status", [
            'profile_id' => $profileId,
            'status' => $status,
            'reason' => $reason,
        ]);

        // Здесь можно добавить логику для обработки изменения статуса профиля
        // Например, отключить отправку сообщений, если профиль offline
        // или отправить уведомление администратору
    }

    /**
     * Обработать входящее сообщение
     */
    protected function handleIncomingMessage(array $data): void
    {
        Log::info("Incoming WhatsApp message received", [
            'from' => $data['from'] ?? null,
            'body' => $data['body'] ?? null,
            'message_id' => $data['id'] ?? null,
        ]);

        // Здесь можно добавить логику для обработки входящих сообщений
        // Например, автоматические ответы или сохранение в базу
    }

    /**
     * Обработать исходящее сообщение через API
     */
    protected function handleOutgoingMessageApi(array $data): void
    {
        $messageId = $data['id'] ?? null;
        $taskId = $data['task_id'] ?? null;

        if (empty($messageId) && empty($taskId)) {
            return;
        }

        // Обновляем external_message_id, если его еще нет
        $notification = null;
        
        if ($taskId) {
            $notification = Notification::where('external_task_id', $taskId)
                ->where('channel', 'whatsapp')
                ->first();
        }

        if ($notification && empty($notification->external_message_id) && $messageId) {
            $notification->update([
                'external_message_id' => $messageId,
            ]);
            
            Log::info("Updated notification with external_message_id", [
                'notification_id' => $notification->id,
                'external_message_id' => $messageId,
            ]);
        }
    }

    /**
     * Обработать исходящее сообщение с телефона
     */
    protected function handleOutgoingMessagePhone(array $data): void
    {
        Log::debug("Outgoing message from phone", [
            'message_id' => $data['id'] ?? null,
            'from' => $data['from'] ?? null,
        ]);

        // Сообщения, отправленные с телефона, обычно не связаны с нашими уведомлениями
    }
}

