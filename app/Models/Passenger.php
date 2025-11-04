<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Passenger extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'external_booking_id',
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'phone',
        'seat_number',
        'ticket_price',
        'ticket_status',
    ];

    protected function casts(): array
    {
        return [
            'ticket_price' => 'decimal:2',
        ];
    }

    // Связи
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->last_name,
            $this->first_name,
            $this->middle_name,
        ]);
        
        return implode(' ', $parts);
    }

    // Methods
    public function hasEmail(): bool
    {
        return !empty($this->email);
    }

    public function hasPhone(): bool
    {
        return !empty($this->phone);
    }

    public function canReceiveNotifications(): bool
    {
        return $this->hasEmail() || $this->hasPhone();
    }
}

