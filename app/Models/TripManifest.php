<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripManifest extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'external_route_id',
        'external_trip_id',
        'dt_depart',
        'dt_arrive',
        'from_id',
        'to_id',
        'from_name',
        'to_name',
        'route_number',
        'bus_number',
        'vehicle_model',
        'carrier_name',
        'created_by',
        'updated_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'dt_depart' => 'datetime',
            'dt_arrive' => 'datetime',
        ];
    }

    // Relationships
    public function items()
    {
        return $this->hasMany(TripManifestItem::class, 'manifest_id');
    }

    public function passengers()
    {
        return $this->hasManyThrough(
            Passenger::class,
            TripManifestItem::class,
            'manifest_id', // FK в trip_manifest_items
            'id', // FK в passengers
            'id', // Локальный ключ в trip_manifests
            'passenger_id' // Локальный ключ в trip_manifest_items
        );
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeByRouteAndDate($query, string $externalRouteId, string $dtDepart)
    {
        return $query->where('external_route_id', $externalRouteId)
            ->where('dt_depart', $dtDepart);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    // Methods
    public function finalize(): void
    {
        $this->update(['status' => 'final']);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isFinal(): bool
    {
        return $this->status === 'final';
    }

    /**
     * Получить количество пассажиров с явкой
     */
    public function getCheckedInCount(): int
    {
        return $this->items()->where('checked_in', true)->count();
    }

    /**
     * Получить количество пассажиров с неявкой
     */
    public function getNotCheckedInCount(): int
    {
        return $this->items()->where('checked_in', false)->count();
    }

    /**
     * Получить количество пассажиров без отметки
     */
    public function getPendingCount(): int
    {
        return $this->items()->whereNull('checked_in')->count();
    }
}
