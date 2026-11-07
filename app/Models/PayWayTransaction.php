<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PayWayTransaction extends Model
{
    protected $table = 'payway_transactions';

    protected $fillable = [
        'user_id', 'guest_token', 'tran_id', 'purpose', 'payable_type', 'payable_id', 'amount', 'currency',
        'status', 'apv', 'payment_type', 'bank_ref', 'last_response', 'paid_at', 'expires_at',
    ];

    protected $casts = [
        'amount'        => 'float',
        'last_response' => 'array',
        'paid_at'       => 'datetime',
        'expires_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isFinal(): bool
    {
        return in_array($this->status, ['paid', 'failed', 'cancelled', 'refunded'], true);
    }

    /** ≤20 chars (PayWay limit): RT + yymmddHHMMSS + 6 random digits. */
    public static function newTranId(): string
    {
        do {
            $id = 'RT' . now()->format('ymdHis') . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::where('tran_id', $id)->exists());

        return $id;
    }
}
