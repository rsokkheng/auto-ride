<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerContract extends Model
{
    protected $fillable = [
        'partner_id',
        'base_fee',
        'per_km_rate',
        'surcharge_small',
        'surcharge_medium',
        'surcharge_large',
        'min_fee',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    /** Calculate delivery fee for a given distance and package size. */
    public function calculateFee(float $distanceKm, string $packageSize = 'small'): int
    {
        $surchargeKey = 'surcharge_' . $packageSize;
        $surcharge    = $this->{$surchargeKey} ?? 0;

        $raw = $this->base_fee + (int) ceil($distanceKm * $this->per_km_rate) + $surcharge;
        $fee = (int) (ceil($raw / 100) * 100);

        return max($fee, (int) $this->min_fee);
    }
}
