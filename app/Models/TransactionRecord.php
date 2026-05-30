<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TransactionRecord extends Model
{
    protected $fillable = [
        'reference_id',
        'reference_type',
        'payer_id',
        'payee_id',
        'type',
        'payment_method',
        'payment_by',
        'gross_amount',
        'platform_fee',
        'company_share',
        'driver_earning',
        'status',
        'note',
        'processed_by',
        'processed_at',
    ];

    protected $casts = [
        'gross_amount'   => 'integer',
        'platform_fee'   => 'integer',
        'company_share'  => 'integer',
        'driver_earning' => 'integer',
        'processed_at'   => 'datetime',
    ];

    // ── Human-readable labels ───────────────────────────────────────────────

    public static array $methodLabels = [
        'cash'         => 'Cash',
        'wallet'       => 'Wallet',
        'aba'          => 'ABA Bank',
        'wing'         => 'Wing Money',
        'other_online' => 'Online',
    ];

    public static array $methodIcons = [
        'cash'         => 'fa-money-bill-wave',
        'wallet'       => 'fa-wallet',
        'aba'          => 'fa-university',
        'wing'         => 'fa-mobile-alt',
        'other_online' => 'fa-globe',
    ];

    public static array $methodColors = [
        'cash'         => 'secondary',
        'wallet'       => 'primary',
        'aba'          => 'success',
        'wing'         => 'info',
        'other_online' => 'warning',
    ];

    public static array $typeLabels = [
        'delivery_payment' => 'Delivery Payment',
        'ride_payment'     => 'Ride Payment',
        'top_up'           => 'Top-up',
        'withdrawal'       => 'Withdrawal',
        'refund'           => 'Refund',
        'salary'           => 'Salary',
    ];

    public static array $statusColors = [
        'pending'   => 'warning',
        'completed' => 'success',
        'cancelled' => 'secondary',
        'refunded'  => 'danger',
    ];

    // ── Relationships ───────────────────────────────────────────────────────

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payee_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCash(): bool
    {
        return $this->payment_method === 'cash';
    }
}
