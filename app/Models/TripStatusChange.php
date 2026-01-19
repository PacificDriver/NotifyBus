<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripStatusChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'old_status',
        'new_status',
        'reason',
        'new_departure_at',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'new_departure_at' => 'datetime',
            'changed_at' => 'datetime',
        ];
    }

    // Отключаем стандартные timestamps, так как используем changed_at
    public $timestamps = false;

    // Связи
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
