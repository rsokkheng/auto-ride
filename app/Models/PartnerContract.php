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
    /**
     * Delivery fee for a Partner package: the partner's active contract, or
     * the default partner prices (admin pricing settings) without one.
     */
    public static function feeFor(User $partner, string $serviceOption = 'normal', string $packageSize = 'small'): int
    {
        $contract = self::where('partner_id', $partner->id)->where('is_active', true)->latest()->first();
        if ($contract) return $contract->calculateFee($serviceOption, $packageSize);

        $base = $serviceOption === 'express'
            ? (int) PricingSetting::get('partner_express_fee', 10000)
            : (int) PricingSetting::get('partner_normal_fee', 5000);
        $surcharge = match ($packageSize) {
            'large'       => (int) PricingSetting::get('partner_surcharge_large', 5000),
            'extra_large' => (int) PricingSetting::get('partner_surcharge_extra_large', 5000),
            default       => 0,
        };
        return $base + $surcharge;
    }

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
