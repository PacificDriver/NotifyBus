<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Station extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'city',
        'region',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    // Связи
    public function departureRoutes()
    {
        return $this->hasMany(Route::class, 'departure_station_id');
    }

    public function arrivalRoutes()
    {
        return $this->hasMany(Route::class, 'arrival_station_id');
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}


