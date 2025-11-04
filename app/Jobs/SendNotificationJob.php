<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Services\WhatsAppService;
use App\Services\EmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;
    public $backoff = [10, 30, 60]; // Задержка между попытками в секундах

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Notification $notification
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Проверяем, не было ли уведомление уже отправлено
            if ($this->notification->status === 'sent' || $this->notification->status === 'delivered') {
                Log::info("Notification {$this->notification->id} already sent, skipping");
                return;
            }

            $this->notification->markAsQueued();

            // Отправляем в зависимости от канала
            if ($this->notification->isEmail()) {
                $this->sendEmail();
            } elseif ($this->notification->isWhatsApp()) {
                $this->sendWhatsApp();
            }

            // Обновляем статус
            $this->notification->markAsSent();
            
            // Обновляем счетчик в задаче
            $this->notification->notificationTask->incrementSentCount();

            Log::info("Notification {$this->notification->id} sent successfully via {$this->notification->channel}");

        } catch (\Exception $e) {
            $this->handleFailure($e);
        }
    }

    /**
     * Отправка Email
     */
    protected function sendEmail(): void
    {
        $emailService = app(EmailService::class);
        
        $emailService->send(
            to: $this->notification->recipient,
            subject: $this->notification->subject,
            body: $this->notification->message,
            metadata: $this->notification->metadata ?? []
        );
    }

    /**
     * Отправка WhatsApp
     */
    protected function sendWhatsApp(): void
    {
        $whatsappService = app(WhatsAppService::class);
        
        $result = $whatsappService->send(
            to: $this->notification->recipient,
            message: $this->notification->message,
            metadata: $this->notification->metadata ?? []
        );

        // Сохраняем внешние ID сообщения/задачи
        $updateData = [];
        
        if (isset($result['message_id'])) {
            $updateData['external_message_id'] = $result['message_id'];
        }
        if (isset($result['task_id'])) {
            $updateData['external_task_id'] = $result['task_id'];
        }
        
        // Также сохраняем в metadata для совместимости
        $metadata = $this->notification->metadata ?? [];
        if (isset($result['message_id'])) {
            $metadata['external_message_id'] = $result['message_id'];
        }
        if (isset($result['task_id'])) {
            $metadata['external_task_id'] = $result['task_id'];
        }
        $updateData['metadata'] = $metadata;
        
        $this->notification->update($updateData);
    }

    /**
     * Обработка неудачной отправки
     */
    protected function handleFailure(\Exception $e): void
    {
        $this->notification->incrementRetryCount();

        Log::error("Failed to send notification {$this->notification->id}: {$e->getMessage()}", [
            'notification_id' => $this->notification->id,
            'channel' => $this->notification->channel,
            'recipient' => $this->notification->recipient,
            'retry_count' => $this->notification->retry_count,
            'exception' => $e->getMessage(),
        ]);

        // Если достигли максимального количества попыток
        if (!$this->notification->canRetry($this->tries)) {
            $this->notification->markAsFailed($e->getMessage());
            $this->notification->notificationTask->incrementFailedCount();
            
            Log::error("Notification {$this->notification->id} failed after {$this->tries} attempts");
        } else {
            // Попробуем еще раз
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Job failed for notification {$this->notification->id}", [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $this->notification->markAsFailed($exception->getMessage());
        $this->notification->notificationTask->incrementFailedCount();
    }
}


