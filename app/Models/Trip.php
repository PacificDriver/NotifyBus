<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'trip_number',
        'external_id',
        'departure_time',
        'arrival_time',
        'status',
        'cancellation_reason',
        'cancelled_at',
        'delay_minutes',
        'total_seats',
        'available_seats',
    ];

    protected function casts(): array
    {
        return [
            'departure_time' => 'datetime',
            'arrival_time' => 'datetime',
            'cancelled_at' => 'datetime',
            'delay_minutes' => 'integer',
            'total_seats' => 'integer',
            'available_seats' => 'integer',
        ];
    }

    // Accessors для конвертации времени в часовой пояс Asia/Sakhalin
    protected function departureTime(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                if (!$value) {
                    return null;
                }
                // Если значение уже Carbon объект
                if ($value instanceof \Carbon\Carbon) {
                    return $value->setTimezone('Asia/Sakhalin')->subHours(3);
                }
                // Если строка, парсим как UTC и конвертируем в Asia/Sakhalin, вычитаем 3 часа
                return \Carbon\Carbon::parse($value, 'UTC')->setTimezone('Asia/Sakhalin')->subHours(3);
            },
            set: function ($value) {
                if (!$value) {
                    return null;
                }
                // Сохраняем как есть (Laravel сохранит в UTC)
                return $value;
            }
        );
    }

    protected function arrivalTime(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                if (!$value) {
                    return null;
                }
                if ($value instanceof \Carbon\Carbon) {
                    return $value->setTimezone('Asia/Sakhalin')->subHours(3);
                }
                return \Carbon\Carbon::parse($value, 'UTC')->setTimezone('Asia/Sakhalin')->subHours(3);
            },
            set: function ($value) {
                if (!$value) {
                    return null;
                }
                return $value;
            }
        );
    }

    protected function cancelledAt(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value) {
                if (!$value) {
                    return null;
                }
                if ($value instanceof \Carbon\Carbon) {
                    return $value->setTimezone('Asia/Sakhalin');
                }
                return \Carbon\Carbon::parse($value, 'UTC')->setTimezone('Asia/Sakhalin');
            },
            set: function ($value) {
                if (!$value) {
                    return null;
                }
                return $value;
            }
        );
    }

    // Связи
    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function passengers()
    {
        return $this->hasMany(Passenger::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // Scopes
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeDelayed($query)
    {
        return $query->where('status', 'delayed');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('departure_time', [$startDate, $endDate]);
    }

    // Methods
    public function cancel(string $reason): void
    {
        $this->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
            'cancelled_at' => now('Asia/Sakhalin'),
        ]);
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isDelayed(): bool
    {
        return $this->status === 'delayed';
    }
}

