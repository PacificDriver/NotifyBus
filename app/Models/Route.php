<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    use HasFactory;

    protected $fillable = [
        'departure_station_id',
        'arrival_station_id',
        'route_number',
        'duration_minutes',
        'distance_km',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'distance_km' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    // Связи
    public function departureStation()
    {
        return $this->belongsTo(Station::class, 'departure_station_id');
    }

    public function arrivalStation()
    {
        return $this->belongsTo(Station::class, 'arrival_station_id');
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Accessor
    public function getFullRouteNameAttribute(): string
    {
        return "{$this->departureStation->name} → {$this->arrivalStation->name}";
    }
}


