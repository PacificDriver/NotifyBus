<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_task_id',
        'passenger_id',
        'trip_id',
        'channel',
        'recipient',
        'subject',
        'message',
        'status',
        'error_message',
        'retry_count',
        'queued_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    // Связи
    public function notificationTask()
    {
        return $this->belongsTo(NotificationTask::class);
    }

    public function passenger()
    {
        return $this->belongsTo(Passenger::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    // Methods
    public function markAsQueued(): void
    {
        $this->update([
            'status' => 'queued',
            'queued_at' => now(),
        ]);
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'failed_at' => now(),
        ]);
    }

    public function incrementRetryCount(): void
    {
        $this->increment('retry_count');
    }

    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->retry_count < $maxRetries;
    }

    public function isEmail(): bool
    {
        return $this->channel === 'email';
    }

    public function isWhatsApp(): bool
    {
        return $this->channel === 'whatsapp';
    }
}

