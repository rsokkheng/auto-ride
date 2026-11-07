<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Evening close between the accountant and a staff driver. */
class DriverClosing extends Model
{
    protected $fillable = [
        'driver_id', 'business_date', 'packages_count', 'returned_count',
        'expected_cash_khr', 'expected_cash_usd', 'expected_bank_khr', 'expected_bank_usd',
        'received_cash_khr', 'received_cash_usd', 'bank_verified', 'khr_per_usd',
        'difference_khr', 'note', 'closed_by', 'closed_at',
    ];

    protected $casts = [
        'business_date' => 'date:Y-m-d', 'closed_at' => 'datetime', 'bank_verified' => 'boolean',
        'expected_cash_usd' => 'float', 'expected_bank_usd' => 'float', 'received_cash_usd' => 'float',
    ];

    public function driver(): BelongsTo { return $this->belongsTo(User::class, 'driver_id'); }
    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
}
