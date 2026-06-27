<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MarketplaceProduct extends Model
{
    protected $fillable = [
        'seller_id',
        'category_id',
        'vehicle_id',
        'title',
        'slug',
        'description',
        'condition',
        'listing_type',
        'price',
        'rent_price_per_day',
        'quantity',
        'status',
        'location_text',
        'location_lat',
        'location_lng',
        'views_count',
        'expires_at',
    ];

    protected $casts = [
        'price'             => 'decimal:2',
        'rent_price_per_day'=> 'decimal:2',
        'quantity'          => 'integer',
        'views_count'       => 'integer',
        'location_lat'      => 'float',
        'location_lng'      => 'float',
        'expires_at'        => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->title) . '-' . Str::random(6);
            }
        });
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'category_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(MarketplaceProductImage::class, 'product_id')->orderBy('sort_order');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MarketplaceOrder::class, 'product_id');
    }
}
