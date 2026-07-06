<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Settlement extends Model
{
    protected $fillable = [
        'settlement_type', 'user_id', 'period_start', 'period_end', 'status',
        'rides_count', 'deliveries_count', 'gross_earnings', 'commission_total',
        'tips_total', 'cod_collected',
        'orders_count', 'delivery_fees', 'cod_handled',
        'adjustments', 'adjustment_note', 'net_payout',
        'payment_method', 'bank_name', 'bank_account', 'account_holder', 'payment_reference',
        'notes', 'created_by', 'approved_by', 'approved_at',
        'processed_by', 'processed_at', 'paid_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end'   => 'date',
        'approved_at'  => 'datetime',
        'processed_at' => 'datetime',
        'paid_at'      => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'draft'      => 'secondary',
            'pending'    => 'warning',
            'approved'   => 'info',
            'processing' => 'primary',
            'paid'       => 'success',
            'failed'     => 'danger',
            'cancelled'  => 'dark',
            default      => 'secondary',
        };
    }

    public function canApprove(): bool   { return $this->status === 'pending'; }
    public function canProcess(): bool   { return $this->status === 'approved'; }
    public function canMarkPaid(): bool  { return $this->status === 'processing'; }
    public function canMarkFailed(): bool { return $this->status === 'processing'; }
    public function canCancel(): bool    { return in_array($this->status, ['draft', 'pending']); }
    public function canDelete(): bool    { return $this->status === 'draft'; }
}
