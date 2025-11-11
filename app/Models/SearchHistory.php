<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SearchHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'from_station_id',
        'to_station_id',
        'from_station_name',
        'to_station_name',
        'trip_date',
        'cancelled_count',
        'result_count',
        'search_type',
        'query_payload',
    ];

    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
            'query_payload' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function fromStation()
    {
        return $this->belongsTo(Station::class, 'from_station_id');
    }

    public function toStation()
    {
        return $this->belongsTo(Station::class, 'to_station_id');
    }
}


