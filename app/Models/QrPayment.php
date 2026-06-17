<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class QrPayment extends Model
{
    protected $fillable = [
        'user_id', 'reference', 'amount_khr', 'status', 'payment_type',
        'payable_type', 'payable_id', 'qr_data', 'bank_ref',
        'expires_at', 'paid_at',
    ];

    protected $casts = [
        'amount_khr' => 'integer',
        'expires_at' => 'datetime',
        'paid_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isExpired(): bool
    {
        return $this->status === 'pending' && now()->gt($this->expires_at);
    }

    public static function generateReference(): string
    {
        return 'QR' . strtoupper(substr(md5(uniqid('', true)), 0, 10));
    }
}
