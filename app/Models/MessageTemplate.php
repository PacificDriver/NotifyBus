<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
        'subject',
        'body',
        'available_variables',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'available_variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    // Связи
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notificationTasks()
    {
        return $this->hasMany(NotificationTask::class, 'template_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Methods
    /**
     * Заменяет переменные в шаблоне на реальные данные
     */
    public function render(array $data): array
    {
        $subject = $this->subject;
        $body = $this->body;

        foreach ($data as $key => $value) {
            $placeholder = "{{" . $key . "}}";
            $subject = str_replace($placeholder, $value, $subject);
            $body = str_replace($placeholder, $value, $body);
        }

        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }

    /**
     * Получить переменные для конкретного пассажира и рейса
     */
    public static function getVariablesForPassenger(Passenger $passenger, Trip $trip): array
    {
        return [
            'passenger_full_name' => $passenger->full_name,
            'passenger_first_name' => $passenger->first_name,
            'passenger_last_name' => $passenger->last_name,
            'trip_number' => $trip->trip_number,
            'departure_station' => $trip->route->departureStation->name,
            'arrival_station' => $trip->route->arrivalStation->name,
            'departure_time' => $trip->departure_time->format('d.m.Y H:i'),
            'departure_date' => $trip->departure_time->format('d.m.Y'),
            'departure_time_only' => $trip->departure_time->format('H:i'),
            'arrival_time' => $trip->arrival_time->format('d.m.Y H:i'),
            'seat_number' => $passenger->seat_number ?? 'не указано',
            'cancellation_reason' => $trip->cancellation_reason ?? '',
            'delay_minutes' => $trip->delay_minutes ?? 0,
        ];
    }
}


