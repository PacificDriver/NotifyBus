<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'created_by',
        'races_data',
        'trip_ids',
        'template_id',
        'custom_message',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'scheduled_at',
        'started_at',
        'completed_at',
    ];

    /**
     * Boot метод для автоматической генерации названия при создании
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($task) {
            // Если название не указано, генерируем автоматически на основе текущей даты и времени
            // Используем часовой пояс Asia/Sakhalin (UTC+11)
            if (empty($task->title)) {
                $task->title = 'Рассылка уведомлений - ' . now('Asia/Sakhalin')->format('d.m.Y H:i:s');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'races_data' => 'array',
            'trip_ids' => 'array',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    // Связи
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function template()
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function trips()
    {
        return Trip::whereIn('id', $this->trip_ids ?? [])->get();
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // Methods
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update([
            'status' => 'failed',
        ]);
    }

    public function incrementSentCount(): void
    {
        $this->increment('sent_count');
    }

    public function incrementFailedCount(): void
    {
        $this->increment('failed_count');
    }

    public function getSuccessRate(): float
    {
        if ($this->total_recipients === 0) {
            return 0;
        }

        return round(($this->sent_count / $this->total_recipients) * 100, 2);
    }
}


