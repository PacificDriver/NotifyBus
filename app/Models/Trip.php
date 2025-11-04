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
            'cancelled_at' => now(),
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

