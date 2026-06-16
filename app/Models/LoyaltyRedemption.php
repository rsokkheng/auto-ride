<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyRedemption extends Model
{
    protected $fillable = [
        'user_id',
        'points_redeemed',
        'credit_amount_khr',
        'description',
    ];

    protected $casts = [
        'points_redeemed'  => 'integer',
        'credit_amount_khr'=> 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
