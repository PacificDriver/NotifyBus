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

            // Логируем начало отправки
            $startLogData = [
                'notification_id' => $this->notification->id,
                'channel' => $this->notification->channel,
                'recipient' => $this->notification->recipient,
                'task_id' => $this->notification->notification_task_id,
                'passenger_id' => $this->notification->passenger_id,
                'trip_id' => $this->notification->trip_id,
                'status' => 'queued',
                'queued_at' => now('Asia/Sakhalin')->toDateTimeString(),
            ];

            if ($this->notification->isEmail()) {
                $startLogData['subject'] = $this->notification->subject;
                $startLogData['message_length'] = strlen($this->notification->message ?? '');
                Log::info("📧 НАЧАЛО ОТПРАВКИ: Email", $startLogData);
            } elseif ($this->notification->isWhatsApp()) {
                $startLogData['message_length'] = strlen($this->notification->message ?? '');
                Log::info("📱 НАЧАЛО ОТПРАВКИ: WhatsApp", $startLogData);
            }

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

            // Подробное логирование успешной отправки
            $logData = [
                'notification_id' => $this->notification->id,
                'channel' => $this->notification->channel,
                'recipient' => $this->notification->recipient,
                'task_id' => $this->notification->notification_task_id,
                'passenger_id' => $this->notification->passenger_id,
                'trip_id' => $this->notification->trip_id,
                'status' => 'sent',
                'sent_at' => now('Asia/Sakhalin')->toDateTimeString(),
            ];

            if ($this->notification->isEmail()) {
                $logData['subject'] = $this->notification->subject;
                $logData['message_length'] = strlen($this->notification->message ?? '');
                Log::info("✅ УВЕДОМЛЕНИЕ ОТПРАВЛЕНО: Email", $logData);
            } elseif ($this->notification->isWhatsApp()) {
                $logData['message_length'] = strlen($this->notification->message ?? '');
                $logData['external_message_id'] = $this->notification->external_message_id ?? null;
                $logData['external_task_id'] = $this->notification->external_task_id ?? null;
                Log::info("✅ УВЕДОМЛЕНИЕ ОТПРАВЛЕНО: WhatsApp", $logData);
            }

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

        $logData = [
            'notification_id' => $this->notification->id,
            'channel' => $this->notification->channel,
            'recipient' => $this->notification->recipient,
            'task_id' => $this->notification->notification_task_id,
            'passenger_id' => $this->notification->passenger_id,
            'trip_id' => $this->notification->trip_id,
            'retry_count' => $this->notification->retry_count,
            'max_retries' => $this->tries,
            'error_message' => $e->getMessage(),
            'error_class' => get_class($e),
            'timestamp' => now('Asia/Sakhalin')->toDateTimeString(),
        ];

        if ($this->notification->isEmail()) {
            $logData['subject'] = $this->notification->subject;
            $logData['message_length'] = strlen($this->notification->message ?? '');
        } elseif ($this->notification->isWhatsApp()) {
            $logData['message_length'] = strlen($this->notification->message ?? '');
        }

        // Если достигли максимального количества попыток
        if (!$this->notification->canRetry($this->tries)) {
            $this->notification->markAsFailed($e->getMessage());
            $this->notification->notificationTask->incrementFailedCount();
            
            $logData['status'] = 'failed';
            $logData['failed_at'] = now('Asia/Sakhalin')->toDateTimeString();
            $logData['final_attempt'] = true;
            
            if ($this->notification->isEmail()) {
                Log::error("❌ УВЕДОМЛЕНИЕ НЕ ОТПРАВЛЕНО: Email (после {$this->tries} попыток)", $logData);
            } elseif ($this->notification->isWhatsApp()) {
                Log::error("❌ УВЕДОМЛЕНИЕ НЕ ОТПРАВЛЕНО: WhatsApp (после {$this->tries} попыток)", $logData);
            }
        } else {
            $logData['will_retry'] = true;
            $logData['next_retry_delay'] = $this->backoff[$this->notification->retry_count - 1] ?? 60;
            
            if ($this->notification->isEmail()) {
                Log::warning("⚠️ ОШИБКА ОТПРАВКИ Email (попытка {$this->notification->retry_count}/{$this->tries}), будет повтор", $logData);
            } elseif ($this->notification->isWhatsApp()) {
                Log::warning("⚠️ ОШИБКА ОТПРАВКИ WhatsApp (попытка {$this->notification->retry_count}/{$this->tries}), будет повтор", $logData);
            }
            
            // Попробуем еще раз
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $logData = [
            'notification_id' => $this->notification->id,
            'channel' => $this->notification->channel,
            'recipient' => $this->notification->recipient,
            'task_id' => $this->notification->notification_task_id,
            'passenger_id' => $this->notification->passenger_id,
            'trip_id' => $this->notification->trip_id,
            'retry_count' => $this->notification->retry_count,
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
            'error_class' => get_class($exception),
            'failed_at' => now('Asia/Sakhalin')->toDateTimeString(),
            'trace' => $exception->getTraceAsString(),
        ];

        if ($this->notification->isEmail()) {
            $logData['subject'] = $this->notification->subject;
            $logData['message_length'] = strlen($this->notification->message ?? '');
            Log::error("❌ ЗАДАЧА ПРОВАЛЕНА: Email (job failed)", $logData);
        } elseif ($this->notification->isWhatsApp()) {
            $logData['message_length'] = strlen($this->notification->message ?? '');
            Log::error("❌ ЗАДАЧА ПРОВАЛЕНА: WhatsApp (job failed)", $logData);
        }

        $this->notification->markAsFailed($exception->getMessage());
        $this->notification->notificationTask->incrementFailedCount();
    }
}


