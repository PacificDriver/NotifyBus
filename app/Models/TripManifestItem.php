<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripManifestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'manifest_id',
        'passenger_id',
        'checked_in',
        'checked_in_at',
        'checked_in_by',
    ];

    protected function casts(): array
    {
        return [
            'checked_in' => 'boolean',
            'checked_in_at' => 'datetime',
        ];
    }

    // Relationships
    public function manifest()
    {
        return $this->belongsTo(TripManifest::class, 'manifest_id');
    }

    public function passenger()
    {
        return $this->belongsTo(Passenger::class, 'passenger_id');
    }

    public function checkedInBy()
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    // Scopes
    public function scopeCheckedIn($query)
    {
        return $query->where('checked_in', true);
    }

    public function scopeNotCheckedIn($query)
    {
        return $query->where('checked_in', false);
    }

    public function scopePending($query)
    {
        return $query->whereNull('checked_in');
    }

    // Methods
    public function markCheckedIn(int $userId): void
    {
        $this->update([
            'checked_in' => true,
            'checked_in_at' => now(),
            'checked_in_by' => $userId,
        ]);
    }

    public function markNotCheckedIn(int $userId): void
    {
        $this->update([
            'checked_in' => false,
            'checked_in_at' => now(),
            'checked_in_by' => $userId,
        ]);
    }

    public function clearCheckIn(): void
    {
        $this->update([
            'checked_in' => null,
            'checked_in_at' => null,
            'checked_in_by' => null,
        ]);
    }
}
