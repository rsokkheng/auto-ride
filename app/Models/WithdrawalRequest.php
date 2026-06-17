<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'driver_id', 'amount_khr', 'status',
        'payment_method', 'account_number', 'account_name', 'bank_name',
        'admin_note', 'processed_at', 'processed_by',
    ];

    protected $casts = [
        'amount_khr'   => 'integer',
        'processed_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
