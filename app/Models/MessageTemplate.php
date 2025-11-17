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
     * @param array|null $raceData Данные из races_data для использования правильного trip_number
     */
    public static function getVariablesForPassenger(Passenger $passenger, Trip $trip, ?array $raceData = null): array
    {
        // Убеждаемся, что связи загружены
        if (!$trip->relationLoaded('route')) {
            $trip->load('route.departureStation', 'route.arrivalStation');
        } elseif ($trip->route && !$trip->route->relationLoaded('departureStation')) {
            $trip->route->load('departureStation', 'arrivalStation');
        }

        // Получаем названия станций с проверками
        // ПРИОРИТЕТ: сначала из raceData, потом из Trip->route
        $departureStation = 'не указано';
        $arrivalStation = 'не указано';
        
        // Используем станции из raceData, если они есть
        if ($raceData) {
            // Пробуем разные варианты названий станций из raceData
            $departureStation = $raceData['from_station_name'] 
                ?? $raceData['departure_station_name'] 
                ?? $raceData['route_start']
                ?? null;
            
            $arrivalStation = $raceData['to_station_name'] 
                ?? $raceData['arrival_station_name'] 
                ?? $raceData['route_end']
                ?? null;
            
            \Illuminate\Support\Facades\Log::debug("Stations from raceData", [
                'from_station_name' => $raceData['from_station_name'] ?? null,
                'to_station_name' => $raceData['to_station_name'] ?? null,
                'departure_station_found' => $departureStation !== null,
                'arrival_station_found' => $arrivalStation !== null,
            ]);
        }
        
        // Если не нашли в raceData, используем из Trip->route
        if (($departureStation === null || $departureStation === 'не указано') && $trip->route) {
            if ($trip->route->departureStation) {
                $departureStation = $trip->route->departureStation->name;
            }
        }
        
        if (($arrivalStation === null || $arrivalStation === 'не указано') && $trip->route) {
            if ($trip->route->arrivalStation) {
                $arrivalStation = $trip->route->arrivalStation->name;
            }
        }
        
        // Если все еще не указано, устанавливаем значение по умолчанию
        if (empty($departureStation) || $departureStation === null) {
            $departureStation = 'не указано';
        }
        if (empty($arrivalStation) || $arrivalStation === null) {
            $arrivalStation = 'не указано';
        }

        // route_number - номер маршрута (например, 505)
        // ПРИОРИТЕТ: 1) route_number из races_data, 2) route_number из Route модели
        $routeNumber = 'N/A';
        if ($raceData) {
            if (isset($raceData['route_number']) && !empty($raceData['route_number'])) {
                $routeNumber = $raceData['route_number'];
            } elseif ($trip->route && $trip->route->route_number) {
                $routeNumber = $trip->route->route_number;
            }
        } else {
            if ($trip->route && $trip->route->route_number) {
                $routeNumber = $trip->route->route_number;
            }
        }
        
        // trip_number - номер рейса (например, 518)
        // ПРИОРИТЕТ: 1) trip_number из races_data, 2) trip_number из Trip модели, 3) route_number из races_data, 4) id из races_data
        $tripNumber = 'N/A';
        if ($raceData) {
            if (isset($raceData['trip_number']) && !empty($raceData['trip_number'])) {
                $tripNumber = $raceData['trip_number'];
            } elseif ($trip->trip_number) {
                $tripNumber = $trip->trip_number;
            } elseif (isset($raceData['route_number']) && !empty($raceData['route_number'])) {
                // Если нет trip_number, используем route_number как fallback
                $tripNumber = $raceData['route_number'];
            } else {
                $tripNumber = $raceData['id'] ?? 'N/A';
            }
        } else {
            $tripNumber = $trip->trip_number ?? 'N/A';
        }
        
        \Illuminate\Support\Facades\Log::debug("Route and trip numbers", [
            'race_data_route_number' => $raceData['route_number'] ?? null,
            'race_data_trip_number' => $raceData['trip_number'] ?? null,
            'race_data_id' => $raceData['id'] ?? null,
            'trip_route_route_number' => $trip->route->route_number ?? null,
            'trip_trip_number' => $trip->trip_number ?? null,
            'final_route_number' => $routeNumber,
            'final_trip_number' => $tripNumber,
        ]);

        // Получаем часовой пояс из конфига (по умолчанию Asia/Sakhalin = UTC+11)
        $timezone = config('app.timezone', 'Asia/Sakhalin');
        
        // Форматируем время с учетом часового пояса
        $formatTime = function($carbonDate) use ($timezone) {
            if (!$carbonDate) {
                return 'не указано';
            }
            // Преобразуем в нужный часовой пояс и форматируем
            return $carbonDate->setTimezone($timezone);
        };

        $departureTime = $trip->departure_time ? $formatTime($trip->departure_time) : null;
        $arrivalTime = $trip->arrival_time ? $formatTime($trip->arrival_time) : null;

        return [
            'passenger_full_name' => $passenger->full_name ?? 'Пассажир',
            'passenger_first_name' => $passenger->first_name ?? '',
            'passenger_last_name' => $passenger->last_name ?? '',
            'trip_number' => $tripNumber, // Для обратной совместимости, использует route_number
            'route_number' => $routeNumber, // Номер маршрута для пользователя (например, 505)
            'departure_station' => $departureStation,
            'arrival_station' => $arrivalStation,
            'departure_time' => $departureTime ? $departureTime->format('d.m.Y H:i') : 'не указано',
            'departure_date' => $departureTime ? $departureTime->format('d.m.Y') : 'не указано',
            'departure_time_only' => $departureTime ? $departureTime->format('H:i') : 'не указано',
            'arrival_time' => $arrivalTime ? $arrivalTime->format('d.m.Y H:i') : 'не указано',
            'seat_number' => $passenger->seat_number ?? 'не указано',
            'cancellation_reason' => $trip->cancellation_reason ?? '',
            'delay_minutes' => $trip->delay_minutes ?? 0,
        ];
    }
}


