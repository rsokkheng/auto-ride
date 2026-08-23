<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MarketplaceVehicleType extends Model
{
    protected $fillable = ['name_km', 'name_en', 'slug', 'sort_order', 'active'];

    protected $casts = [
        'active'     => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Sizes valid for this vehicle type — e.g. Passenger: 1.4M/1.6M, Cargo: 1.4M/1.8M/2.2M. */
    public function sizes(): BelongsToMany
    {
        return $this->belongsToMany(MarketplaceVehicleSize::class, 'marketplace_vehicle_type_size')
            ->orderBy('marketplace_vehicle_sizes.sort_order');
    }

    /** Body-style categories valid for this vehicle type — e.g. Passenger: ធម្មតា; Cargo: បើកបូល/ដំបូលក្លុបបិទជិត. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(MarketplaceCategory::class, 'marketplace_vehicle_type_category', 'marketplace_vehicle_type_id', 'marketplace_category_id')
            ->orderBy('marketplace_categories.sort_order');
    }

    /** Colors valid for this vehicle type. */
    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(MarketplaceVehicleColor::class, 'marketplace_vehicle_type_color', 'marketplace_vehicle_type_id', 'marketplace_vehicle_color_id')
            ->orderBy('marketplace_vehicle_colors.sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
