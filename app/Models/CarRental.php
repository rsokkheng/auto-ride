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
        'guest_token',
        'guest_fcm_token',
        'vehicle_id',
        'marketplace_product_id',
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

    protected $hidden = ['guest_token', 'guest_fcm_token'];

    /** Not persisted: 'buyer' | 'seller' | 'admin' — who triggered the current save, so they aren't notified about their own action. */
    public ?string $actedBy = null;

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

    public function marketplaceProduct(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'marketplace_product_id');
    }
}
