<?php

namespace App\Models;

use App\Traits\HasLocalizedName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipTier extends Model
{
    use HasLocalizedName;

    protected $fillable = [
        'name', 'name_en', 'name_kh', 'name_zh', 'slug', 'min_points', 'badge_color', 'icon', 'benefits',
        'ride_discount_pct', 'delivery_discount_pct', 'points_multiplier',
        'priority_support', 'free_cancellations', 'sort_order',
    ];

    protected $casts = [
        'benefits'               => 'array',
        'priority_support'       => 'boolean',
        'free_cancellations'     => 'boolean',
        'min_points'             => 'integer',
        'ride_discount_pct'      => 'integer',
        'delivery_discount_pct'  => 'integer',
        'points_multiplier'      => 'integer',
        'sort_order'             => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'membership_tier_id');
    }

    public static function forPoints(int $points): ?self
    {
        return static::where('min_points', '<=', $points)
            ->orderByDesc('min_points')
            ->first();
    }
}
