<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Driver extends Authenticatable
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'username',
        'password',
        'phone',
        'email',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Связи
    public function trips()
    {
        return $this->belongsToMany(Trip::class, 'driver_trip')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function assignedBy()
    {
        return $this->belongsToMany(User::class, 'driver_trip', 'driver_id', 'assigned_by');
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

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    // Methods
    public function activate(): void
    {
        $this->update(['is_active' => true]);
    }

    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }

    public function assignTrip(Trip $trip, User $assignedBy): void
    {
        if (!$this->trips()->where('trip_id', $trip->id)->exists()) {
            $this->trips()->attach($trip->id, [
                'assigned_by' => $assignedBy->id,
                'assigned_at' => now('Asia/Sakhalin'),
            ]);
        }
    }

    public function unassignTrip(Trip $trip): void
    {
        $this->trips()->detach($trip->id);
    }

    public function hasTrip(Trip $trip): bool
    {
        return $this->trips()->where('trip_id', $trip->id)->exists();
    }
}
