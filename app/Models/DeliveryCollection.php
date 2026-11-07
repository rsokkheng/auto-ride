<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Money a staff driver verified receiving for a Partner package (mixed cash ៛/$ and bank). */
class DeliveryCollection extends Model
{
    protected $fillable = [
        'delivery_id', 'driver_id', 'partner_id', 'business_date', 'expected_khr',
        'cash_khr', 'cash_usd', 'bank_khr', 'bank_usd', 'bank_name', 'bank_ref',
        'khr_per_usd', 'total_khr', 'difference_khr', 'note', 'driver_closing_id',
    ];

    protected $casts = [
        'business_date' => 'date:Y-m-d',
        'cash_usd' => 'float', 'bank_usd' => 'float',
        'expected_khr' => 'integer', 'cash_khr' => 'integer', 'bank_khr' => 'integer',
        'total_khr' => 'integer', 'difference_khr' => 'integer', 'khr_per_usd' => 'integer',
    ];

    public function delivery(): BelongsTo { return $this->belongsTo(Delivery::class); }
    public function driver(): BelongsTo { return $this->belongsTo(User::class, 'driver_id'); }

    /** Payment mix label, e.g. "Cash ៛ + Bank (ABA)". */
    public function methodLabel(): string
    {
        $parts = [];
        if ($this->cash_khr > 0) $parts[] = 'Cash ៛';
        if ($this->cash_usd > 0) $parts[] = 'Cash $';
        if ($this->bank_khr > 0 || $this->bank_usd > 0) $parts[] = 'Bank' . ($this->bank_name ? " ({$this->bank_name})" : '');
        return $parts ? implode(' + ', $parts) : '—';
    }
}
