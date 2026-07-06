<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerContract extends Model
{
    protected $fillable = [
        'partner_id',
        'normal_fee',
        'express_fee',
        'surcharge_large',
        'surcharge_extra_large',
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

    /**
     * Flat-rate fee: normal/express base + size surcharge.
     * Normal 5000, Express 10000, Large +5000, Extra Large +5000.
     */
    public function calculateFee(string $serviceOption = 'normal', string $packageSize = 'small'): int
    {
        $base = $serviceOption === 'express' ? (int) $this->express_fee : (int) $this->normal_fee;

        $surcharge = match ($packageSize) {
            'large'       => (int) $this->surcharge_large,
            'extra_large' => (int) $this->surcharge_extra_large,
            default       => 0,
        };

        return $base + $surcharge;
    }
}
