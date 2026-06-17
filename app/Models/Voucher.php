<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = [
        'title', 'description', 'image', 'category', 'discount_type',
        'discount_value', 'min_fare', 'max_discount', 'total_limit',
        'per_user_limit', 'points_required', 'valid_from', 'valid_until', 'active',
    ];

    protected $casts = [
        'active'          => 'boolean',
        'valid_from'      => 'datetime',
        'valid_until'     => 'datetime',
        'discount_value'  => 'integer',
        'min_fare'        => 'integer',
        'max_discount'    => 'integer',
        'total_limit'     => 'integer',
        'per_user_limit'  => 'integer',
        'points_required' => 'integer',
    ];

    public function userVouchers(): HasMany
    {
        return $this->hasMany(UserVoucher::class);
    }

    public function isAvailableTo(int $userId): bool
    {
        if (! $this->active) return false;
        if ($this->valid_from && now()->lt($this->valid_from)) return false;
        if ($this->valid_until && now()->gt($this->valid_until)) return false;

        if ($this->total_limit > 0) {
            $claimed = $this->userVouchers()->count();
            if ($claimed >= $this->total_limit) return false;
        }

        if ($this->per_user_limit > 0) {
            $userClaimed = $this->userVouchers()->where('user_id', $userId)->count();
            if ($userClaimed >= $this->per_user_limit) return false;
        }

        return true;
    }

    public function calculateDiscount(int $fare): int
    {
        if ($fare < $this->min_fare) return 0;

        if ($this->discount_type === 'flat') {
            return $this->discount_value;
        }

        $discount = (int) round($fare * $this->discount_value / 100);

        if ($this->max_discount > 0) {
            $discount = min($discount, $this->max_discount);
        }

        return $discount;
    }
}
