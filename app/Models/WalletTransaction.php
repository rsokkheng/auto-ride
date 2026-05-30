<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'direction',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference_id',
        'reference_type',
        'status',
        'note',
        'created_by',
    ];

    protected $casts = [
        'amount'         => 'integer',
        'balance_before' => 'integer',
        'balance_after'  => 'integer',
    ];

    // Convenience label map for display.
    public static array $typeLabels = [
        'top_up'              => 'Top-up',
        'trip_earning'        => 'Trip Earning',
        'salary'              => 'Salary',
        'platform_commission' => 'Platform Fee',
        'company_commission'  => 'Company Fee',
        'rental_fee'          => 'Rental Fee',
        'withdrawal'          => 'Withdrawal',
        'bonus'               => 'Bonus',
        'adjustment'          => 'Adjustment',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function getSignedAmountAttribute(): int
    {
        return $this->direction === 'credit' ? $this->amount : -$this->amount;
    }
}
