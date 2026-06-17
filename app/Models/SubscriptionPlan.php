<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'price_khr', 'billing_cycle',
        'ride_credit_khr', 'ride_discount_pct', 'delivery_discount_pct',
        'free_cancellations', 'surge_waived', 'priority_matching',
        'bonus_points_pct', 'features', 'badge_color', 'icon',
        'active', 'sort_order',
    ];

    protected $casts = [
        'price_khr'             => 'integer',
        'ride_credit_khr'       => 'integer',
        'ride_discount_pct'     => 'integer',
        'delivery_discount_pct' => 'integer',
        'free_cancellations'    => 'integer',
        'bonus_points_pct'      => 'integer',
        'surge_waived'          => 'boolean',
        'priority_matching'     => 'boolean',
        'active'                => 'boolean',
        'features'              => 'array',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class);
    }

    public function activeSubscribersCount(): int
    {
        return $this->subscriptions()->where('status', 'active')->count();
    }

    public function scopeActive($query)
    {
        return $query->where('active', true)->orderBy('sort_order');
    }
}
