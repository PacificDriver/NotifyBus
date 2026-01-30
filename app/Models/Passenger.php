<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Passenger extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'external_race_id',
        'external_booking_id',
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'phone',
        'birth_date',
        'document_type',
        'document_series',
        'document_number',
        'document_issued_at',
        'seat_number',
        'ticket_price',
        'ticket_service_fee',
        'ticket_total_price',
        'ticket_discount',
        'ticket_status',
        'passenger_type',
        'external_order_id',
        'ticket_uid',
        'ticket_number',
        'ticket_purchased_at',
        'external_payload',
        'data_source',
        'purchase_source',
    ];

    protected function casts(): array
    {
        return [
            'ticket_price' => 'decimal:2',
            'ticket_service_fee' => 'decimal:2',
            'ticket_total_price' => 'decimal:2',
            'ticket_discount' => 'decimal:2',
            'birth_date' => 'date',
            'document_issued_at' => 'date',
            'ticket_purchased_at' => 'datetime',
            'external_payload' => 'array',
            'data_source' => 'string',
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

    /**
     * Получить группу email настроек на основе источника данных
     */
    public function getEmailGroup(): string
    {
        return $this->data_source === 'startport' ? 'startport_email' : 'email';
    }
}

