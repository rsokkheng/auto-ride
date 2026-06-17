<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id', 'subscription_plan_id', 'status', 'payment_method',
        'started_at', 'expires_at', 'cancelled_at', 'renewed_at',
        'auto_renew', 'used_ride_credit_khr', 'used_cancellations', 'renewal_count',
    ];

    protected $casts = [
        'started_at'           => 'datetime',
        'expires_at'           => 'datetime',
        'cancelled_at'         => 'datetime',
        'renewed_at'           => 'datetime',
        'auto_renew'           => 'boolean',
        'used_ride_credit_khr' => 'integer',
        'used_cancellations'   => 'integer',
        'renewal_count'        => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SubscriptionTransaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function remainingCreditKhr(): int
    {
        return max(0, ($this->plan->ride_credit_khr ?? 0) - $this->used_ride_credit_khr);
    }

    public function remainingCancellations(): int|string
    {
        $free = $this->plan->free_cancellations ?? 0;
        if ($free === 0) return 'Unlimited';

        return max(0, $free - $this->used_cancellations);
    }

    public function expiresInDays(): int
    {
        if (! $this->expires_at) return 999;

        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }
}
