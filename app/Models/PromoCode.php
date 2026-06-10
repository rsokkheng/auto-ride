<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = [
        'code', 'description', 'type', 'value', 'min_order', 'max_discount',
        'usage_limit', 'used_count', 'per_user_limit', 'service_type',
        'active', 'starts_at', 'expires_at',
    ];

    protected $casts = [
        'active'     => 'boolean',
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function usages()
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    public function isValid(string $serviceType, int $orderAmount, int $userId): bool
    {
        if (! $this->active) return false;
        if ($this->starts_at && now()->lt($this->starts_at)) return false;
        if ($this->expires_at && now()->gt($this->expires_at)) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;
        if ($orderAmount < $this->min_order) return false;

        if ($this->service_type !== 'all' && $this->service_type !== $serviceType) return false;

        $userUsages = $this->usages()->where('user_id', $userId)->count();
        if ($userUsages >= $this->per_user_limit) return false;

        return true;
    }

    public function calculateDiscount(int $orderAmount): int
    {
        if ($this->type === 'percent') {
            $discount = (int) round($orderAmount * $this->value / 100);
            if ($this->max_discount) {
                $discount = min($discount, $this->max_discount);
            }
            return $discount;
        }
        return min($this->value, $orderAmount);
    }
}
