<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarRental extends Model
{
    protected $fillable = [
        'user_id',
        'entry_type',
        'guest_name',
        'guest_phone',
        'vehicle_id',
        'vehicle_type',
        'pickup_location',
        'pickup_lat',
        'pickup_lng',
        'start_date',
        'end_date',
        'total_days',
        'daily_rate_khr',
        'total_amount_khr',
        'payment_method',
        'status',
        'notes',
        'confirmed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date'       => 'date',
        'end_date'         => 'date',
        'total_days'       => 'integer',
        'daily_rate_khr'   => 'integer',
        'total_amount_khr' => 'integer',
        'pickup_lat'       => 'float',
        'pickup_lng'       => 'float',
        'confirmed_at'     => 'datetime',
        'cancelled_at'     => 'datetime',
    ];

    public function isGuest(): bool
    {
        return $this->entry_type === 'guest';
    }

    public function renterName(): string
    {
        return $this->isGuest() ? ($this->guest_name ?? 'Guest') : ($this->user?->name ?? '—');
    }

    public function renterPhone(): ?string
    {
        return $this->isGuest() ? $this->guest_phone : $this->user?->phone;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }
}
